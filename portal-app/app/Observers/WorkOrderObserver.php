<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Services\Activity;
use App\Services\CompletionReport;
use App\Services\Microsoft\ReportFiler;
use App\Services\Microsoft\SharePoint;

class WorkOrderObserver
{
    /**
     * When a job is marked complete, its PDF completion report is created and attached automatically,
     * then copied to SharePoint (after the page has been sent, so nobody waits for the upload).
     */
    public function updated(WorkOrder $wo): void
    {
        if ($wo->wasChanged('status') && $wo->status === 'complete') {
            $report = CompletionReport::generate($wo);
            Activity::log('work_order.report', "Completion report created for {$wo->ref()}.", $wo, [], $wo->account_id);

            if (SharePoint::isConfigured()) {
                app()->runningInConsole()
                    ? ReportFiler::file($report)
                    : app()->terminating(fn () => ReportFiler::file($report->fresh() ?? $report));
            }
        }
    }
}
