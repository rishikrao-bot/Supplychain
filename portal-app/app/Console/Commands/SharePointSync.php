<?php

namespace App\Console\Commands;

use App\Services\Microsoft\ReportFiler;
use App\Services\Microsoft\SharePoint;
use Illuminate\Console\Command;

/** Copies any completion reports that aren't in SharePoint yet (new set-up, or an earlier upload failed). */
class SharePointSync extends Command
{
    protected $signature = 'portal:sharepoint-sync {--limit=50}';

    protected $description = 'Copy completion reports that are not yet in SharePoint';

    public function handle(): int
    {
        if (! SharePoint::isConfigured()) {
            $this->line('SharePoint is not set up; nothing to do.');

            return self::SUCCESS;
        }
        $done = $failed = 0;
        foreach (ReportFiler::pending((int) $this->option('limit')) as $report) {
            ReportFiler::file($report) ? $done++ : $failed++;
        }
        $this->info("Copied {$done} report(s) to SharePoint.".($failed ? " {$failed} failed; see the log." : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
