<?php

namespace App\Console\Commands;

use App\Services\Microsoft\Dataverse;
use App\Services\Microsoft\MicrosoftApp;
use App\Services\Microsoft\SharePoint;
use Illuminate\Console\Command;
use Throwable;

/** Checks each Microsoft 365 connection and says in plain words what works and what to fix. */
class MicrosoftCheck extends Command
{
    protected $signature = 'portal:microsoft-check';

    protected $description = 'Test the Microsoft 365 connections (sign-in, SharePoint, Dataverse)';

    public function handle(): int
    {
        $ok = true;
        foreach (self::results() as $r) {
            $line = str_pad($r['name'], 22).$r['message'];
            match ($r['state']) {
                'ok' => $this->info('✔ '.$line),
                'off' => $this->line('– '.$line),
                default => $this->error('✘ '.$line),
            };
            $ok = $ok && $r['state'] !== 'fail';
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, array{name: string, state: 'ok'|'off'|'fail', message: string}> */
    public static function results(): array
    {
        $out = [];
        if (! MicrosoftApp::isConfigured()) {
            return [['name' => 'App registration', 'state' => 'off', 'message' => 'Not set up yet (MICROSOFT_TENANT_ID / CLIENT_ID / CLIENT_SECRET in .env).']];
        }
        try {
            MicrosoftApp::token('https://graph.microsoft.com/.default');
            $out[] = ['name' => 'App registration', 'state' => 'ok', 'message' => 'The portal can sign in to Microsoft 365.'];
        } catch (Throwable $e) {
            return [['name' => 'App registration', 'state' => 'fail', 'message' => $e->getMessage()]];
        }

        $out[] = config('services.microsoft.sign_in')
            ? ['name' => 'Microsoft sign-in', 'state' => 'ok', 'message' => 'Shown on the login page for your own staff.']
            : ['name' => 'Microsoft sign-in', 'state' => 'off', 'message' => 'Turned off (MICROSOFT_SIGN_IN=false).'];

        if (! SharePoint::isConfigured()) {
            $out[] = ['name' => 'SharePoint', 'state' => 'off', 'message' => 'No site set (SHAREPOINT_SITE).'];
        } else {
            try {
                $lib = SharePoint::library();
                $out[] = ['name' => 'SharePoint', 'state' => 'ok', 'message' => 'Reports go to '.$lib['webUrl'].'/'.config('services.microsoft.sharepoint_folder')];
            } catch (Throwable $e) {
                $out[] = ['name' => 'SharePoint', 'state' => 'fail', 'message' => $e->getMessage()];
            }
        }

        if (! Dataverse::isConfigured()) {
            $out[] = ['name' => 'Dataverse CRM', 'state' => 'off', 'message' => 'No environment set (DATAVERSE_URL).'];
        } else {
            try {
                $me = Dataverse::whoAmI();
                $out[] = ['name' => 'Dataverse CRM', 'state' => 'ok', 'message' => 'Connected as application user '.($me['UserId'] ?? '?').'.'];
            } catch (Throwable $e) {
                $out[] = ['name' => 'Dataverse CRM', 'state' => 'fail', 'message' => $e->getMessage()];
            }
        }

        return $out;
    }
}
