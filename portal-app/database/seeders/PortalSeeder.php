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

/**
 * Starting data for the live portal: the Compass MEP account, Wates as the first
 * client with the schedule of rates sent on 11 Nov 2026, contracts, the
 * competency list and the rules. Safe to run more than once.
 *
 * Sites, partners and people are NOT seeded: they are real data entered in the portal.
 */
class PortalSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::firstOrCreate(['slug' => 'compass-mep'], [
            'name' => 'Compass MEP', 'brand_colour' => '#294653', 'accent_colour' => '#25C4A9', 'logo_path' => 'img/compass-mep-logo.png',
        ]);
        $a = $account->id;

        Organisation::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'type' => 'compass'], [
            'name' => 'Compass MEP Ltd', 'address' => '20 Garrett Street, London, EC1Y 0TW',
            'zones' => ['A', 'B', 'C'],
        ]);

        $wates = Organisation::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'type' => 'client', 'name' => 'Wates'], [
            // Payment terms are still to be agreed at onboarding.
            'settings' => ['afp_day' => 25, 'due_days' => 7, 'final_days' => 30, 'portal_certify' => true, 'terms_status' => 'on_hold'],
        ]);

        $card = RateCard::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'client_id' => $wates->id, 'name' => 'Wates Schedule of Rates (11 Nov 2026)'], [
            'valid_from' => '2026-11-11', 'status' => 'active',
        ]);
        $rates = [
            ['labour.building.normal', 'Building services engineer, normal hours', 65, 'per_hour'],
            ['labour.building.ooh', 'Building services engineer, out of hours', 97.50, 'per_hour'],
            ['labour.building.sun', 'Building services engineer, Sunday / bank holiday', 130, 'per_hour'],
            ['labour.specialist.normal', 'Specialist systems engineer, normal hours', 80, 'per_hour'],
            ['labour.specialist.ooh', 'Specialist systems engineer, out of hours', 120, 'per_hour'],
            ['labour.specialist.sun', 'Specialist systems engineer, Sunday / bank holiday', 160, 'per_hour'],
            ['labour.lift.normal', 'Lift engineer, normal hours', 185, 'per_hour'],
            ['labour.lift.ooh', 'Lift engineer, out of hours (TBC)', null, 'per_hour'],
            ['labour.lift.sun', 'Lift engineer, Sunday / bank holiday (TBC)', null, 'per_hour'],
            ['callout.emergency', 'Call-out: emergency (within 12 hours)', 95, 'per_visit'],
            ['callout.standard', 'Call-out: standard (planned, urgent or routine)', 55, 'per_visit'],
            ['zone.b', 'Zone B supplement', 75, 'per_visit'],
            ['markup.materials', 'Materials, plant hire and waste markup', 15, 'percent'],
            ['fee.specialist', 'Specialist management fee', 12.5, 'percent'],
            ['fee.specialist_min', 'Specialist management fee minimum', 50, 'fixed'],
            ['fee.inspection', 'Inspection and quote visit', 120, 'fixed'],
            ['day.apm', 'Assistant Project Manager / Assistant QS (day)', 350, 'per_day'],
            ['day.pm', 'Project Manager / QS / Engineer (day)', 500, 'per_day'],
            ['day.spm', 'Senior Project Manager / Senior QS / Senior Engineer (day)', 650, 'per_day'],
            ['day.cm', 'Commissioning Manager (day)', 700, 'per_day'],
            ['day.lcm', 'Senior / Lead Commissioning Manager (day)', 750, 'per_day'],
        ];
        foreach ($rates as [$code, $label, $amount, $unit]) {
            $card->items()->updateOrCreate(['code' => $code], ['label' => $label, 'amount' => $amount, 'unit' => $unit]);
        }

        foreach ([['Castle Point', 'Castle Point Borough Council (corporate, non-dwelling estate)'], ['South East Corporate Estate', null]] as [$name, $end]) {
            Contract::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'client_id' => $wates->id, 'name' => $name], ['end_client' => $end, 'rate_card_id' => $card->id]);
        }

        $groups = [
            'Mechanical' => ['Plumbing & heating' => null, 'Gas (Gas Safe)' => 'Gas Safe', 'Commercial boilers' => null, 'HVAC & air conditioning (F-Gas)' => 'F-Gas', 'Ventilation & extract' => null, 'Pumps & pressurisation' => null, 'Pool plant & filtration' => null, 'Refrigeration' => null],
            'Electrical' => ['Electrical installation & repair' => 'NICEIC', 'Emergency lighting' => null, 'EICR testing' => 'NICEIC', 'HV, generators & UPS' => null, 'Lightning protection' => null],
            'Specialist systems' => ['Fire alarm' => 'BAFE', 'Sprinklers' => null, 'Smoke ventilation' => null, 'BMS / controls' => null, 'Intruder alarm' => 'NSI', 'CCTV' => 'NSI', 'Access control' => 'NSI', 'IT / data' => null],
            'Lifts & access' => ['Passenger lifts' => 'LEIA', 'Platform & stair lifts' => 'LEIA', 'Doors & shutters' => null, 'Scaffolding & access equipment' => null],
            'Building fabric' => ["Builder's work" => null, 'Roofing' => null, 'Waterproofing' => null, 'Drainage' => null, 'Passive fire protection' => null, 'Fire doors' => null, 'Groundworks' => null],
            'Compliance & surveys' => ['Asbestos surveys' => 'UKAS', 'Asbestos removal (licensed)' => 'HSE asbestos licence', 'Water hygiene / legionella' => 'Legionella Control Association', 'Structural engineering' => null, 'Fire risk assessment' => null],
            'Site services' => ['Waste removal' => null, 'Traffic management' => null, 'Ductwork & extract cleaning' => null],
        ];
        $sort = 0;
        foreach ($groups as $groupName => $items) {
            $g = CompetencyGroup::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'name' => $groupName], ['sort' => $sort++]);
            foreach ($items as $name => $cert) {
                Competency::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'competency_group_id' => $g->id, 'name' => $name], ['certificate_required' => $cert]);
            }
        }

        // Rules. "status" shows what is agreed and what is still under discussion with the client.
        $settings = [
            ['return_visit_callout', false, 'agreed', 'Agreed in principle with Wates on 18 Nov 2026: no call-out on return visits. Proposal clause 8 to be updated.'],
            ['multi_trade_callout', 'per_trade', 'on_hold', 'Second trade on the same visit: Compass to come back to Wates.'],
            ['approval_policy', 'except_emergency', 'proposed', 'Wates approval before dispatch, except emergencies.'],
            ['approval_threshold', 500, 'proposed', 'Used only if approval is required above a value.'],
            ['inspection_credit_days', 180, 'on_hold', 'Leon offered to extend from 90 days.'],
            ['inspection_review_months', 6, 'on_hold', 'Review of unconverted quotes, proposed by Leon.'],
            ['offer_reply_hours', 4, 'agreed', 'Reply deadline for planned, urgent and routine offers.'],
            ['offer_reply_minutes_emergency', 30, 'agreed', 'Reply deadline for emergency offers.'],
            ['show_cis', false, 'on_hold', 'CIS hidden for now.'],
            ['completion_report_delivery', 'portal_download', 'agreed', 'Completion reports are downloaded from the portal, not emailed (decided 19 Nov 2026).'],
            ['partner_pay_days', 30, 'on_hold', 'Stage 2: partner payment terms.'],
        ];
        foreach ($settings as [$key, $value, $status, $note]) {
            Setting::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'key' => $key], ['value' => $value, 'status' => $status, 'note' => $note]);
        }

        NumberSequence::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'name' => 'work_order'], ['next_value' => 1001]);
        NumberSequence::withoutGlobalScopes()->firstOrCreate(['account_id' => $a, 'name' => 'quote'], ['next_value' => 1]);
    }
}
