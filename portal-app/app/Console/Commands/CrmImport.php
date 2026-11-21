<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Organisation;
use App\Models\Task;
use App\Models\User;
use App\Services\Microsoft\Dataverse;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One-off move of the old Dataverse CRM into the portal: accounts → companies, contacts, opportunities →
 * pipeline, open tasks, and notes, phone calls and appointments → activity history.
 * Safe to run more than once: rows already imported are updated, not duplicated. Owners are matched to
 * portal users by email. Use --dry-run first to see the counts.
 */
class CrmImport extends Command
{
    protected $signature = 'portal:crm-import {--dry-run : Show what would be imported without saving}';

    protected $description = 'Import companies, contacts, pipeline, tasks and history from the Dataverse CRM';

    private Account $account;

    /** Dataverse systemuserid => portal user id */
    private array $owners = [];

    /** Dataverse ids => portal ids, per table */
    private array $map = ['org' => [], 'contact' => [], 'opp' => []];

    public function handle(): int
    {
        if (! Dataverse::isConfigured()) {
            $this->error('Dataverse is not set up (DATAVERSE_URL and the Microsoft app settings in .env).');

            return self::FAILURE;
        }
        $this->account = Account::where('slug', 'compass-mep')->first() ?? Account::firstOrFail();
        $dry = (bool) $this->option('dry-run');

        $portalUsers = User::withoutGlobalScopes()->where('account_id', $this->account->id)->pluck('id', 'email')->mapWithKeys(fn ($id, $e) => [Str::lower($e) => $id]);
        foreach (Dataverse::all('systemusers', ['$select' => 'systemuserid,internalemailaddress']) as $u) {
            if ($id = $portalUsers[Str::lower((string) ($u['internalemailaddress'] ?? ''))] ?? null) {
                $this->owners[$u['systemuserid']] = $id;
            }
        }

        $accounts = Dataverse::all('accounts', ['$select' => 'accountid,name,telephone1,emailaddress1,websiteurl,address1_line1,address1_city,address1_postalcode,description,customertypecode,industrycode,_ownerid_value', '$filter' => 'statecode eq 0']);
        $contacts = Dataverse::all('contacts', ['$select' => 'contactid,firstname,lastname,jobtitle,emailaddress1,telephone1,mobilephone,description,_parentcustomerid_value,_ownerid_value', '$filter' => 'statecode eq 0']);
        $opps = Dataverse::all('opportunities', ['$select' => 'opportunityid,name,estimatedvalue,estimatedclosedate,closeprobability,description,statecode,_parentaccountid_value,_parentcontactid_value,_customerid_value,_ownerid_value,actualclosedate']);
        $tasks = Dataverse::all('tasks', ['$select' => 'activityid,subject,description,scheduledend,statecode,_regardingobjectid_value,_ownerid_value', '$filter' => 'statecode eq 0']);
        $history = [];
        foreach (['phonecalls' => 'call', 'appointments' => 'meeting'] as $table => $type) {
            foreach (Dataverse::all($table, ['$select' => 'activityid,subject,description,actualend,scheduledstart,createdon,_regardingobjectid_value,_ownerid_value']) as $row) {
                $history[] = $row + ['_type' => $type];
            }
        }
        $notes = Dataverse::all('annotations', ['$select' => 'annotationid,subject,notetext,createdon,_objectid_value,objecttypecode,_ownerid_value', '$filter' => 'isdocument eq false']);

        $this->table(['From Dataverse', 'Rows'], [
            ['Companies (accounts)', count($accounts)], ['Contacts', count($contacts)], ['Pipeline (opportunities)', count($opps)],
            ['Open tasks', count($tasks)], ['Calls and meetings', count($history)], ['Notes', count($notes)],
        ]);
        if ($dry) {
            $this->info('Dry run: nothing saved. Run without --dry-run to import.');

            return self::SUCCESS;
        }

        foreach ($accounts as $a) {
            $org = Organisation::withoutGlobalScopes()->where('account_id', $this->account->id)->where('dataverse_id', $a['accountid'])->first()
                ?? Organisation::withoutGlobalScopes()->where('account_id', $this->account->id)->whereNull('dataverse_id')->where('type', '!=', Organisation::COMPASS)->whereRaw('LOWER(name) = ?', [Str::lower(trim($a['name'] ?? ''))])->first()
                ?? new Organisation(['account_id' => $this->account->id, 'type' => $this->type($a['customertypecode'] ?? null)]);
            $org->fill(array_filter([
                'name' => mb_substr(trim($a['name'] ?? 'Unnamed company'), 0, 160),
                'phone' => $a['telephone1'] ?? null, 'email' => $a['emailaddress1'] ?? null, 'website' => $a['websiteurl'] ?? null,
                'address' => trim(implode(', ', array_filter([$a['address1_line1'] ?? null, $a['address1_city'] ?? null]))) ?: null,
                'postcode' => $a['address1_postalcode'] ?? null, 'notes' => $org->notes ?: ($a['description'] ?? null),
                'owner_id' => $this->owner($a), 'dataverse_id' => $a['accountid'],
            ], fn ($v) => $v !== null && $v !== ''));
            $org->account_id = $this->account->id;
            $org->save();
            $this->map['org'][$a['accountid']] = $org->id;
        }

        foreach ($contacts as $c) {
            $contact = Contact::withoutGlobalScopes()->firstOrNew(['account_id' => $this->account->id, 'dataverse_id' => $c['contactid']]);
            $contact->fill([
                'first_name' => mb_substr(trim($c['firstname'] ?? '') ?: (trim($c['lastname'] ?? '') ?: 'Unknown'), 0, 80),
                'last_name' => trim($c['firstname'] ?? '') !== '' ? ($c['lastname'] ?? null) : null,
                'job_title' => $c['jobtitle'] ?? null, 'email' => $c['emailaddress1'] ?? null, 'phone' => $c['telephone1'] ?? null,
                'mobile' => $c['mobilephone'] ?? null, 'notes' => $c['description'] ?? null, 'owner_id' => $this->owner($c),
                'organisation_id' => $this->map['org'][$c['_parentcustomerid_value'] ?? ''] ?? null,
            ])->save();
            $this->map['contact'][$c['contactid']] = $contact->id;
        }

        foreach ($opps as $o) {
            $opp = Opportunity::withoutGlobalScopes()->firstOrNew(['account_id' => $this->account->id, 'dataverse_id' => $o['opportunityid']]);
            $stage = match ((int) ($o['statecode'] ?? 0)) { 1 => 'won', 2 => 'lost', default => $opp->exists ? $opp->stage : 'qualifying' };
            $orgId = $this->map['org'][$o['_parentaccountid_value'] ?? ''] ?? $this->map['org'][$o['_customerid_value'] ?? ''] ?? null;
            $opp->fill([
                'title' => mb_substr($o['name'] ?? 'Opportunity', 0, 190), 'kind' => $opp->kind ?? 'enquiry', 'stage' => $stage,
                'value' => $o['estimatedvalue'] ?? null, 'probability' => $o['closeprobability'] ?? null,
                'decision_date' => $o['estimatedclosedate'] ?? null, 'notes' => $o['description'] ?? null,
                'organisation_id' => $orgId, 'contact_id' => $this->map['contact'][$o['_parentcontactid_value'] ?? ''] ?? null,
                'owner_id' => $this->owner($o), 'closed_at' => in_array($stage, ['won', 'lost'], true) ? ($o['actualclosedate'] ?? now()) : null,
            ])->save();
            $this->map['opp'][$o['opportunityid']] = $opp->id;
        }

        foreach ($tasks as $t) {
            $task = Task::withoutGlobalScopes()->firstOrNew(['account_id' => $this->account->id, 'dataverse_id' => $t['activityid']]);
            $task->fill(['title' => mb_substr($t['subject'] ?? 'Task', 0, 255), 'notes' => $t['description'] ?? null,
                'due_on' => isset($t['scheduledend']) ? Carbon::parse($t['scheduledend'])->toDateString() : null,
                'status' => $task->status ?? Task::OPEN, 'assigned_to' => $this->owner($t)] + $this->regarding($t['_regardingobjectid_value'] ?? null));
            $task->save();
        }

        foreach ($history as $h) {
            $this->activity($h['activityid'], $h['_type'], $h['subject'] ?? null, $h['description'] ?? null,
                $h['actualend'] ?? $h['scheduledstart'] ?? $h['createdon'] ?? null, $h, $h['_regardingobjectid_value'] ?? null);
        }
        foreach ($notes as $n) {
            $this->activity($n['annotationid'], 'note', $n['subject'] ?? null, $n['notetext'] ?? null, $n['createdon'] ?? null, $n, $n['_objectid_value'] ?? null);
        }

        $this->info('Import finished. Re-running it updates these rows rather than adding new ones.');

        return self::SUCCESS;
    }

    private function type(?int $code): string
    {
        return match ($code) { 3 => Organisation::CLIENT, 10, 11 => Organisation::SUPPLIER, 8, null => Organisation::PROSPECT, default => Organisation::OTHER };
    }

    private function owner(array $row): ?int
    {
        return $this->owners[$row['_ownerid_value'] ?? ''] ?? null;
    }

    /** What a Dataverse activity is "regarding", as portal links. */
    private function regarding(?string $id): array
    {
        if (! $id) {
            return [];
        }
        if (isset($this->map['opp'][$id])) {
            $opp = Opportunity::withoutGlobalScopes()->find($this->map['opp'][$id]);

            return ['opportunity_id' => $opp->id, 'organisation_id' => $opp->organisation_id, 'contact_id' => $opp->contact_id];
        }
        if (isset($this->map['contact'][$id])) {
            $c = Contact::withoutGlobalScopes()->find($this->map['contact'][$id]);

            return ['contact_id' => $c->id, 'organisation_id' => $c->organisation_id];
        }
        if (isset($this->map['org'][$id])) {
            return ['organisation_id' => $this->map['org'][$id]];
        }

        return [];
    }

    private function activity(string $id, string $type, ?string $subject, ?string $body, ?string $at, array $row, ?string $regarding): void
    {
        $links = $this->regarding($regarding);
        if (! $links) {
            return; // not linked to anything that was imported
        }
        $a = CrmActivity::withoutGlobalScopes()->firstOrNew(['account_id' => $this->account->id, 'dataverse_id' => $id]);
        $a->fill(['type' => $type, 'subject' => $subject ? mb_substr($subject, 0, 190) : null, 'body' => $body,
            'occurred_at' => $at ? Carbon::parse($at) : now(), 'user_id' => $this->owner($row)] + $links)->save();
    }
}
