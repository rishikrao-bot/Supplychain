<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\Contract;
use App\Models\NumberSequence;
use App\Models\Organisation;
use App\Models\RateCard;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Starting data: the operator's account, its first clients with their rate cards and contracts,
 * the competency list and the rules. Safe to run more than once.
 *
 * The real data is kept out of the code: on the server it is read from data/portal.json, which is
 * never committed. Without that file, made-up example data (data/portal.example.json) is used,
 * which is what the tests and a fresh development copy get.
 *
 * Sites, partners and people are NOT seeded: they are real data entered in the portal.
 */
class PortalSeeder extends Seeder
{
    public static function dataFile(): string
    {
        $private = __DIR__.'/data/portal.json';

        return is_file($private) && ! app()->runningUnitTests() ? $private : __DIR__.'/data/portal.example.json';
    }

    public function run(): void
    {
        $d = json_decode((string) file_get_contents(self::dataFile()), true) ?? throw new RuntimeException('Starting data file is not valid JSON: '.self::dataFile());

        $account = Account::firstOrCreate(['slug' => $d['account']['slug'] ?? 'main'], array_intersect_key($d['account'], array_flip(['name', 'brand_colour', 'accent_colour', 'logo_path'])));
        $a = $account->id;

        Organisation::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'type' => Organisation::OPERATOR], $d['operator']);

        foreach ($d['clients'] ?? [] as $c) {
            $client = Organisation::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'type' => 'client', 'name' => $c['name']], ['settings' => $c['settings'] ?? null]);
            $card = null;
            if (! empty($c['rate_card'])) {
                $card = RateCard::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'client_id' => $client->id, 'name' => $c['rate_card']['name']], [
                    'valid_from' => $c['rate_card']['valid_from'] ?? null, 'status' => 'active',
                ]);
                foreach ($c['rate_card']['items'] ?? [] as $i) {
                    $card->items()->updateOrCreate(['code' => $i['code']], ['label' => $i['label'], 'amount' => $i['amount'], 'unit' => $i['unit']]);
                }
            }
            foreach ($c['contracts'] ?? [] as $k) {
                Contract::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'client_id' => $client->id, 'name' => $k['name']], ['end_client' => $k['end_client'] ?? null, 'rate_card_id' => $card?->id]);
            }
        }

        $sort = 0;
        foreach ($d['competencies'] ?? [] as $groupName => $items) {
            $g = CompetencyGroup::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'name' => $groupName], ['sort' => $sort++]);
            foreach ($items as $name => $cert) {
                Competency::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'competency_group_id' => $g->id, 'name' => $name], ['certificate_required' => $cert]);
            }
        }

        // Rules. "status" shows what is agreed and what is still under discussion with the client.
        foreach ($d['settings'] ?? [] as $s) {
            Setting::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'key' => $s['key']], ['value' => $s['value'], 'status' => $s['status'], 'note' => $s['note'] ?? null]);
        }

        foreach ($d['sequences'] ?? ['work_order' => 1001, 'quote' => 1] as $name => $next) {
            NumberSequence::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'name' => $name], ['next_value' => $next]);
        }
    }
}
