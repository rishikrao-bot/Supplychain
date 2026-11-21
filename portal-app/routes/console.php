<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copy any completion reports that aren't in SharePoint yet. Needs the cPanel cron job in DEPLOY.md.
Illuminate\Support\Facades\Schedule::command('portal:sharepoint-sync')->everyFifteenMinutes()->withoutOverlapping();
