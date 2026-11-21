<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Dashboard;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Staff;
use App\Support\Weeks;
use ZipArchive;

class ResourcesTest extends PortalTestCase
{
    private function person(string $name, array $attrs = []): Staff
    {
        return Staff::create($attrs + ['account_id' => $this->account->id, 'name' => $name, 'team' => 'Project Management', 'position' => 'MEP Manager', 'days_per_week' => 5]);
    }

    private function proj(string $number, string $name, array $attrs = []): Project
    {
        return Project::create($attrs + ['account_id' => $this->account->id, 'number' => $number, 'name' => $name, 'status' => 'active']);
    }

    // ---- Planner ------------------------------------------------------------------------------

    public function test_office_staff_view_but_only_admins_change_the_plan(): void
    {
        $pat = $this->person('Pat Example');
        $p = $this->proj('P0101', 'Riverside Offices');
        $week = Weeks::ending()->toDateString();

        $this->signIn($this->makeUser($this->operator, 'helpdesk'))->get('/resources')->assertOk()->assertSee('Pat Example');
        $this->get("/resources/people/{$pat->id}")->assertOk()->assertDontSee('Save changes');
        $this->post('/resources/save', ['cells' => ["{$p->id}-{$pat->id}" => [$week => 2]]])->assertForbidden();
        $this->signIn($this->makeUser($this->operator, 'engineer'))->get('/resources')->assertForbidden();
        $this->signIn($this->makeUser($this->client, 'client_admin'))->get('/resources')->assertForbidden();

        $this->signIn($this->makeUser($this->operator, 'admin'))->post('/resources/save', ['cells' => ["{$p->id}-{$pat->id}" => [$week => 2]]])->assertRedirect();
        $this->assertSame(2.0, (float) Allocation::sole()->days);
    }

    public function test_grid_save_rounds_to_half_days_and_blank_removes(): void
    {
        $pat = $this->person('Pat Example');
        $p = $this->proj('P0101', 'Riverside Offices');
        [$w1, $w2] = Weeks::range(Weeks::ending(), 2);
        $this->signIn($this->makeUser($this->operator, 'admin'));
        $this->post('/resources/save', ['cells' => ["{$p->id}-{$pat->id}" => [$w1->toDateString() => '1.3', $w2->toDateString() => '3']]]);
        $this->assertSame([1.5, 3.0], Allocation::orderBy('week')->pluck('days')->map(fn ($d) => (float) $d)->all());
        $this->post('/resources/save', ['cells' => ["{$p->id}-{$pat->id}" => [$w1->toDateString() => '']]]);
        $this->assertSame(1, Allocation::count());
    }

    public function test_fill_weeks_and_utilisation_shows_over_100_percent(): void
    {
        $pat = $this->person('Pat Example');
        $a = $this->proj('P0101', 'Riverside Offices');
        $b = $this->proj('P0102', 'Hilltop School');
        $from = Weeks::ending();
        $this->signIn($this->makeUser($this->operator, 'admin'));
        $this->post('/resources/fill', ['project_id' => $a->id, 'staff_id' => $pat->id, 'days' => 3, 'from' => $from->toDateString(), 'to' => $from->copy()->addWeeks(9)->toDateString(), 'overwrite' => 1])
            ->assertSessionHas('status');
        $this->assertSame(10, Allocation::where('project_id', $a->id)->count());
        // A date mid-week is snapped to that week's Friday.
        $this->post('/resources/fill', ['project_id' => $b->id, 'staff_id' => $pat->id, 'days' => 3, 'from' => $from->copy()->subDays(2)->toDateString(), 'to' => $from->toDateString()]);
        $this->assertSame($from->toDateString(), Allocation::where('project_id', $b->id)->sole()->week->toDateString());

        $this->get('/resources?from='.$from->toDateString())->assertSee('120%')->assertSee('u-over');
        $this->get("/resources/people/{$pat->id}?from=".$from->toDateString())->assertSee('Hilltop School')->assertSee('Riverside Offices');

        // Without overwrite, weeks already planned are left alone.
        $this->post('/resources/fill', ['project_id' => $a->id, 'staff_id' => $pat->id, 'days' => 1, 'from' => $from->toDateString(), 'to' => $from->copy()->addWeeks(11)->toDateString(), 'overwrite' => 0]);
        $this->assertSame(10, Allocation::where('project_id', $a->id)->where('days', 3)->count());
        $this->assertSame(2, Allocation::where('project_id', $a->id)->where('days', 1)->count());
    }

    public function test_new_project_and_person_appear_without_copying_anything(): void
    {
        $this->signIn($this->makeUser($this->operator, 'admin'));
        $this->post('/resources/projects', ['number' => 'P0257', 'name' => 'Canary Wharf fit-out', 'status' => 'active', 'days_allowed' => 40])->assertRedirect();
        $this->post('/resources/people', ['name' => 'New Starter Sam', 'team' => 'Commercial', 'days_per_week' => 4, 'active' => 1, 'starts_on' => now()->addWeeks(3)->toDateString()])->assertRedirect();
        $this->post('/resources/projects', ['number' => 'P0257', 'name' => 'Duplicate', 'status' => 'active'])->assertSessionHasErrors('number');

        $sam = Staff::where('name', 'New Starter Sam')->sole();
        $this->get('/resources')->assertSee('New Starter Sam')->assertSee('New'); // before their start date
        $p = Project::where('number', 'P0257')->sole();
        $this->get("/resources/projects/{$p->id}?add={$sam->id}")->assertOk()->assertSee('New Starter Sam')->assertSee('Save changes');
        $this->get('/resources/projects')->assertSee('Canary Wharf fit-out');
    }

    public function test_csv_export(): void
    {
        $pat = $this->person('Pat Example');
        $p = $this->proj('P0101', 'Riverside Offices');
        Allocation::create(['account_id' => $this->account->id, 'project_id' => $p->id, 'staff_id' => $pat->id, 'week' => Weeks::ending()->toDateString(), 'days' => 2]);
        $res = $this->signIn($this->makeUser($this->operator, 'finance'))->get('/resources/export.csv?from='.Weeks::ending()->toDateString());
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('"Pat Example","Project Management",P0101,"Riverside Offices",2,', $csv);
    }

    // ---- Import of the old spreadsheet ----------------------------------------------------------

    /** A small workbook shaped like the Resources Tracker. Rows: [row => [col letter => value]]. */
    private function workbook(array $sheets): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rt').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE);
        $wb = $rels = '';
        $i = 0;
        foreach ($sheets as $name => [$state, $rows]) {
            $i++;
            $wb .= '<sheet name="'.htmlspecialchars($name).'" sheetId="'.$i.'" '.($state === 'hidden' ? 'state="hidden" ' : '').'r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            $xml = '';
            foreach ($rows as $r => $cells) {
                $xml .= '<row r="'.$r.'">';
                foreach ($cells as $col => $v) {
                    $xml .= is_string($v)
                        ? '<c r="'.$col.$r.'" t="inlineStr"><is><t>'.htmlspecialchars($v).'</t></is></c>'
                        : '<c r="'.$col.$r.'"><v>'.$v.'</v></c>';
                }
                $xml .= '</row>';
            }
            $zip->addFromString("xl/worksheets/sheet{$i}.xml", '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>');
        }
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$wb.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->close();

        return $file;
    }

    public function test_import_merges_project_lists_and_converts_to_days(): void
    {
        $start = 45947; // 17 Oct 2025, a Friday
        $file = $this->workbook([
            'Master_Projects' => ['hidden', [
                3 => ['A' => 'PROJECT NO.', 'B' => 'PROJECT'],
                4 => ['A' => 0, 'B' => 'Holiday'],
                5 => ['A' => 'P0101', 'B' => 'Riverside Offices', 'C' => 'Alex Lead', 'E' => 'TSM', 'H' => 'Client Co', 'I' => 46171],
                6 => ['A' => 'All', 'B' => 'Total Resource'],
            ]],
            'Utilisation' => ['visible', [
                3 => ['A' => 'Project Management', 'B' => 'Alex Lead', 'C' => 'Operations Manager'],
                4 => ['B' => 'Bo Builder', 'C' => 'MEP Manager'],
                5 => ['A' => 'Commercial', 'B' => 'Cy Counter', 'C' => 'Senior QS'],
            ]],
            'Alex Lead' => ['visible', [
                2 => ['A' => 'Alex Lead', 'B' => 'Project Manager'],
                3 => ['C' => $start, 'D' => $start + 7, 'E' => $start + 14],
                4 => ['A' => 0, 'B' => 'Holiday', 'E' => 1],
                5 => ['A' => 'P0101', 'B' => 'Riverside Offices', 'C' => 0.2, 'D' => 0.1],
                6 => ['A' => 'TBC', 'B' => 'Lakeside Leisure Centre', 'D' => 0.4],
                8 => ['B' => 'Total', 'C' => 0.2],
                9 => ['A' => 'P9999', 'B' => 'Below the total row', 'C' => 1],
            ]],
            'Cy_Counter' => ['visible', [
                2 => ['A' => 'Someone Else', 'B' => 'Copied sheet'],
                3 => ['C' => $start],
                4 => ['A' => 'P0101 ', 'B' => ' Riverside  Offices', 'C' => 0.6],
                5 => ['A' => 'TBC ', 'B' => 'Lakeside Leisure Centre', 'C' => 0.2],
                6 => ['B' => 'Total'],
            ]],
            'Old Leaver' => ['hidden', [2 => ['A' => 'Old Leaver'], 3 => ['C' => $start], 4 => ['B' => 'Total']]],
        ]);

        $this->artisan('portal:resources-import', ['file' => $file, '--dry-run' => true])
            ->expectsOutputToContain('"Cy_Counter" has "Someone Else"')->assertSuccessful();
        $this->assertSame(0, Project::count());

        $this->artisan('portal:resources-import', ['file' => $file])->assertSuccessful();
        $this->artisan('portal:resources-import', ['file' => $file])->assertSuccessful(); // re-run: no duplicates

        $this->assertSame(['Alex Lead', 'Cy Counter', 'Old Leaver'], Staff::orderBy('name')->pluck('name')->all());
        $cy = Staff::where('name', 'Cy Counter')->sole();
        $this->assertSame(['Commercial', 'Senior QS'], [$cy->team, $cy->position]);
        $this->assertFalse(Staff::where('name', 'Old Leaver')->sole()->active);

        $this->assertSame(3, Project::count()); // Holiday, Riverside (one, despite spacing), Lakeside (TBC, by name)
        $riverside = Project::where('number', 'P0101')->sole();
        $this->assertSame([$this->client->id, 'TSM', '2026-05-29'], [$riverside->organisation_id, $riverside->type, $riverside->completion_on->toDateString()]);
        $this->assertSame('Alex Lead', $riverside->lead->name);
        $this->assertTrue(Project::where('name', 'Holiday')->sole()->internal);
        $this->assertNull(Project::where('name', 'Lakeside Leisure Centre')->sole()->number);

        $alex = Staff::where('name', 'Alex Lead')->sole();
        $this->assertSame(1.0, (float) Allocation::where(['staff_id' => $alex->id, 'project_id' => $riverside->id, 'week' => '2025-10-17'])->value('days'));
        $this->assertSame(0.5, (float) Allocation::where(['staff_id' => $alex->id, 'project_id' => $riverside->id, 'week' => '2025-10-24'])->value('days'));
        $this->assertSame(3.0, (float) Allocation::where(['staff_id' => $cy->id, 'project_id' => $riverside->id])->value('days'));
        $this->assertSame(6, Allocation::count()); // nothing from below the Total row
    }

    // ---- Power BI reports -----------------------------------------------------------------------

    public function test_admin_adds_power_bi_report_from_its_browser_address(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $this->signIn($admin)->post('/reports', ['title' => 'Monthly performance', 'roles' => ['admin', 'finance'],
            'embed_url' => 'https://app.powerbi.com/groups/1a2b3c4d-0000-0000-0000-000000000001/reports/9f8e7d6c-0000-0000-0000-000000000002/ReportSection?ctid=11111111-2222-3333-4444-555555555555&experience=power-bi'])
            ->assertRedirect();
        $d = Dashboard::sole();
        $this->assertSame('https://app.powerbi.com/reportEmbed?reportId=9f8e7d6c-0000-0000-0000-000000000002&autoAuth=true&groupId=1a2b3c4d-0000-0000-0000-000000000001&ctid=11111111-2222-3333-4444-555555555555', $d->embed_url);
        $this->get("/reports/{$d->id}")->assertOk()->assertSee('<iframe', false)->assertSee('reportEmbed?reportId=9f8e7d6c', false);

        $this->signIn($this->makeUser($this->operator, 'finance'))->get("/reports/{$d->id}")->assertOk();
        $this->signIn($this->makeUser($this->operator, 'helpdesk'))->get("/reports/{$d->id}")->assertForbidden();
        $this->signIn($this->makeUser($this->operator, 'helpdesk'))->get('/reports')->assertForbidden();
        $this->signIn($this->makeUser($this->client, 'client_admin'))->get("/reports/{$d->id}")->assertForbidden();
    }

    public function test_only_power_bi_links_are_accepted(): void
    {
        $this->signIn($this->makeUser($this->operator, 'admin'));
        foreach (['https://evil.example/reportEmbed?reportId=x', 'http://app.powerbi.com/reportEmbed?reportId=x', 'javascript:alert(1)'] as $url) {
            $this->post('/reports', ['title' => 'X', 'roles' => ['admin'], 'embed_url' => $url])->assertSessionHasErrors('embed_url');
        }
        $this->post('/reports', ['title' => 'From iframe code', 'roles' => ['admin'], 'embed_url' => '<iframe title="R" width="1140" height="541.25" src="https://app.powerbi.com/reportEmbed?reportId=abc&amp;autoAuth=true&amp;ctid=t1" frameborder="0" allowFullScreen="true"></iframe>'])->assertRedirect();
        $this->assertSame('https://app.powerbi.com/reportEmbed?reportId=abc&autoAuth=true&ctid=t1', Dashboard::sole()->embed_url);
    }

    // ---- Data API --------------------------------------------------------------------------------

    public function test_api_needs_a_valid_key_and_returns_tables(): void
    {
        $pat = $this->person('Pat Example');
        $p = $this->proj('P0101', 'Riverside Offices', ['days_allowed' => 20]);
        Allocation::create(['account_id' => $this->account->id, 'project_id' => $p->id, 'staff_id' => $pat->id, 'week' => '2026-11-27', 'days' => 4]);
        Opportunity::create(['account_id' => $this->account->id, 'title' => 'Big tender', 'stage' => 'pricing', 'kind' => 'tender', 'value' => 100000, 'probability' => 40]);

        $this->getJson('/api/v1/projects')->assertUnauthorized();
        $this->getJson('/api/v1/projects?key=cmp_wrong')->assertUnauthorized();

        $admin = $this->makeUser($this->operator, 'admin');
        $this->signIn($admin)->post('/microsoft-365/api-keys', ['name' => 'Power BI'])->assertSessionHas('new_api_key');
        $secret = session('new_api_key')['secret'];
        $this->assertStringNotContainsString($secret, (string) ApiKey::sole()->toJson()); // only the hash is stored

        $this->getJson('/api/v1/projects', ['Authorization' => 'Bearer '.$secret])->assertOk()
            ->assertJsonPath('value.0.number', 'P0101')->assertJsonPath('value.0.days_planned', 4);
        $this->getJson('/api/v1/utilisation?key='.$secret)->assertOk()
            ->assertJsonPath('value.0.name', 'Pat Example')->assertJsonPath('value.0.utilisation_pct', 80);
        $this->getJson('/api/v1/opportunities', ['X-Api-Key' => $secret])->assertOk()->assertJsonPath('value.0.weighted_value', 40000);
        foreach (\App\Http\Controllers\Api\DataController::TABLES as $t) {
            $this->getJson("/api/v1/{$t}?key={$secret}")->assertOk()->assertJsonStructure(['value']);
        }
        $this->assertNotNull(ApiKey::sole()->last_used_at);

        $this->signIn($admin)->delete('/microsoft-365/api-keys/'.ApiKey::sole()->id);
        $this->getJson('/api/v1/projects?key='.$secret)->assertUnauthorized();
    }

    public function test_only_operator_admins_create_api_keys(): void
    {
        $this->signIn($this->makeUser($this->operator, 'helpdesk'))->post('/microsoft-365/api-keys', ['name' => 'x'])->assertForbidden();
        $this->assertSame(0, ApiKey::count());
    }
}
