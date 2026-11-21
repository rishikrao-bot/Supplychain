<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Allocation;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Staff;
use App\Support\Weeks;
use App\Support\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings the old "Resources Tracker" spreadsheet into the resource planner:
 *  - people from each person's sheet (sheet name = person), with position and team from "Utilisation";
 *  - one project list made from the master list plus every project typed into any person's sheet;
 *  - every week's figure (fraction of a 5-day week, e.g. 0.2) as days (0.2 → 1 day).
 * Safe to run again with a newer copy: people and projects are matched by name / project number and
 * each week's figure is overwritten. Use --dry-run first.
 */
class ResourcesImport extends Command
{
    protected $signature = 'portal:resources-import {file : The .xlsx file} {--dry-run : Show what would be imported without saving}';

    protected $description = 'Import the Resources Tracker spreadsheet into the resource planner';

    private const SKIP_SHEETS = ['Master_Projects', 'Utilisation'];

    private const INTERNAL = ['holiday', 'uni / college / training', 'overhead'];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }
        $x = new XlsxReader($file);
        $sheets = $x->sheets();
        $warnings = [];

        // 1. Team and position from the Utilisation sheet (team names sit in merged cells: carry them down).
        $util = [];
        if (isset($sheets['Utilisation'])) {
            $team = null;
            foreach ($x->rows('Utilisation', 200, 3) as $r => $cells) {
                if ($r < 3) {
                    continue;
                }
                if (! empty($cells[1]) && is_string($cells[1])) {
                    $team = trim($cells[1]);
                }
                if (! empty($cells[2]) && is_string($cells[2])) {
                    $util[self::key($cells[2])] = ['team' => $team, 'position' => isset($cells[3]) ? trim((string) $cells[3]) : null, 'order' => $r];
                }
            }
        }

        // 2. The master project list (lead, type, client, days allowed, completion).
        $projects = [];
        if (isset($sheets['Master_Projects'])) {
            foreach ($x->rows('Master_Projects', 2000, 9) as $r => $c) {
                if ($r < 4 || empty($c[2]) || ! is_string($c[2]) || ($c[1] ?? '') === 'All') {
                    continue;
                }
                $p = $this->project($projects, $c[1] ?? null, $c[2]);
                $p['lead'] = isset($c[3]) ? trim((string) $c[3]) : null;
                $p['type'] = isset($c[5]) ? trim((string) $c[5]) : null;
                $p['days_allowed'] = isset($c[6]) && is_float($c[6]) ? $c[6] : null;
                $p['client'] = isset($c[8]) ? trim((string) $c[8]) : null;
                if (isset($c[9])) {
                    is_float($c[9]) ? $p['completion_on'] = XlsxReader::date($c[9]) : $p['completion_note'] = trim((string) $c[9]);
                }
                $projects[$p['key']] = $p;
            }
        }

        // 3. Each person's sheet.
        $people = [];
        $cells = [];
        foreach ($sheets as $name => $state) {
            if (in_array($name, self::SKIP_SHEETS, true)) {
                continue;
            }
            $rows = $x->rows($name, 3000, 120);
            $person = trim(str_replace('_', ' ', $name));
            $nameCell = trim((string) ($rows[2][1] ?? ''));
            $u = $util[self::key($person)] ?? null;
            if ($nameCell !== '' && self::key($nameCell) !== self::key($person)) {
                $warnings[] = "Sheet \"{$name}\" has \"{$nameCell}\" in its name cell (copied from another sheet?). Used the sheet name.";
            }
            if (! $u && $state === 'visible') {
                $warnings[] = "{$person} isn't on the Utilisation sheet, so wasn't in the old summary. Now included.";
            }
            $position = $u['position'] ?? ($nameCell !== '' && self::key($nameCell) === self::key($person) ? trim((string) ($rows[2][2] ?? '')) : null);
            $people[$person] = [
                'name' => $person, 'position' => $position ?: null, 'team' => $this->team($u['team'] ?? null, $position),
                'active' => $state === 'visible', 'sort' => $u['order'] ?? 100,
            ];
            $start = $rows[3][3] ?? null;
            if (! is_float($start)) {
                $warnings[] = "Sheet \"{$name}\": no start date in C3; skipped its weeks.";

                continue;
            }
            foreach ($rows as $r => $c) {
                if ($r < 4 || ($c[2] ?? null) === 'Total') {
                    if (($c[2] ?? null) === 'Total') {
                        break;
                    }

                    continue;
                }
                if (empty($c[2]) || ! is_string($c[2])) {
                    continue;
                }
                $p = $this->project($projects, $c[1] ?? null, $c[2]);
                $projects[$p['key']] ??= $p;
                foreach ($c as $col => $v) {
                    if ($col < 3 || ! is_float($v) || $v <= 0) {
                        continue;
                    }
                    $week = Weeks::ending(XlsxReader::date($start + 7 * ($col - 3)))->toDateString();
                    $cells[] = [$p['key'], $person, $week, round($v * 5 * 2) / 2];
                }
            }
        }

        $weeks = array_column($cells, 2);
        $this->table(['From the spreadsheet', ''], [
            ['People', count($people).' ('.count(array_filter($people, fn ($p) => $p['active'])).' visible)'],
            ['Projects (one list)', count($projects)],
            ['Weekly entries', count($cells)],
            ['Weeks', $weeks ? min($weeks).' to '.max($weeks) : '—'],
        ]);
        foreach (array_unique($warnings) as $w) {
            $this->warn($w);
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing saved. Run without --dry-run to import.');

            return self::SUCCESS;
        }

        $account = Account::where('slug', 'compass-mep')->first() ?? Account::firstOrFail();
        DB::transaction(function () use ($account, $people, $projects, $cells) {
            $staffIds = [];
            foreach ($people as $p) {
                $s = Staff::withoutGlobalScopes()->firstOrNew(['account_id' => $account->id, 'name' => $p['name']]);
                $s->fill(['position' => $s->position ?: $p['position'], 'team' => $s->team ?: $p['team'], 'sort' => $p['sort']]);
                if (! $s->exists) {
                    $s->active = $p['active'];
                    $s->days_per_week = 5;
                }
                $s->save();
                $staffIds[self::key($p['name'])] = $s->id;
            }
            $clients = Organisation::withoutGlobalScopes()->where('account_id', $account->id)->where('type', '!=', Organisation::COMPASS)
                ->get(['id', 'name'])->mapWithKeys(fn ($o) => [self::key($o->name) => $o->id]);
            $projectIds = [];
            foreach ($projects as $key => $p) {
                $q = Project::withoutGlobalScopes()->where('account_id', $account->id);
                $model = ($p['number'] ? (clone $q)->where('number', $p['number'])->first() : null)
                    ?? (clone $q)->whereNull('number')->whereRaw('LOWER(name) = ?', [Str::lower($p['name'])])->first()
                    ?? new Project(['account_id' => $account->id, 'status' => 'active']);
                $model->fill(array_filter([
                    'number' => $p['number'], 'name' => $p['name'], 'type' => $p['type'] ?? null,
                    'days_allowed' => $p['days_allowed'] ?? null, 'completion_on' => $p['completion_on'] ?? null,
                    'completion_note' => $p['completion_note'] ?? null,
                    'lead_id' => isset($p['lead']) ? ($staffIds[self::key($p['lead'])] ?? null) : null,
                    'organisation_id' => isset($p['client']) ? ($clients[self::key($p['client'])] ?? null) : null,
                    'client_name' => isset($p['client']) && ! isset($clients[self::key($p['client'])]) ? $p['client'] : null,
                ], fn ($v) => $v !== null && $v !== ''));
                $model->internal = $p['internal'];
                $model->account_id = $account->id;
                $model->save();
                $projectIds[$key] = $model->id;
            }
            foreach ($cells as [$pk, $person, $week, $days]) {
                Allocation::withoutGlobalScopes()->updateOrCreate(
                    ['project_id' => $projectIds[$pk], 'staff_id' => $staffIds[self::key($person)], 'week' => $week],
                    ['account_id' => $account->id, 'days' => $days]);
            }
        });
        $this->info('Imported. Open Resource planner in the portal to check it.');

        return self::SUCCESS;
    }

    /** Find or start a project entry: matched on project number, else on name. */
    private function project(array $projects, mixed $number, string $name): array
    {
        $name = preg_replace('/\s+/', ' ', trim($name));
        $num = is_string($number) ? strtoupper(trim($number)) : null;
        if ($num === '' || $num === 'TBC') {
            $num = null;
        }
        $internal = in_array(Str::lower($name), self::INTERNAL, true) || $number === 0.0;
        $key = $num ? 'n:'.$num : 'p:'.self::key($name);

        return $projects[$key] ?? ['key' => $key, 'number' => $num, 'name' => $name, 'internal' => $internal];
    }

    private function team(?string $team, ?string $position): string
    {
        if ($team) {
            return in_array($team, Staff::TEAMS, true) ? $team : 'Other';
        }
        $pos = Str::lower((string) $position);

        return match (true) {
            str_contains($pos, 'quantity') || str_contains($pos, 'qs') || str_contains($pos, 'commercial') => 'Commercial',
            str_contains($pos, 'design') => 'Design',
            default => 'Project Management',
        };
    }

    private static function key(string $s): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($s)));
    }
}
