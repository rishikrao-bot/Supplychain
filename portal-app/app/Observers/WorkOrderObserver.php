<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Services\Activity;
use App\Services\CompletionReport;

class WorkOrderObserver
{
    /** When a job is marked complete, its PDF completion report is created and attached automatically. */
    public function updated(WorkOrder $wo): void
    {
        if ($wo->wasChanged('status') && $wo->status === 'complete') {
            CompletionReport::generate($wo);
            Activity::log('work_order.report', "Completion report created for {$wo->ref()}.", $wo, [], $wo->account_id);
        }
    }
}
