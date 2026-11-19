<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Services\CompletionReport;
use App\Services\WorkOrderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkOrderReportController extends Controller
{
    public function __invoke(Request $request, WorkOrder $workOrder)
    {
        abort_unless(WorkOrderAccess::canView($request->user(), $workOrder), 403);
        $report = $workOrder->attachments()->where('kind', CompletionReport::KIND)->latest()->first();
        abort_unless($report && Storage::disk('local')->exists($report->path), 404, 'No completion report yet. It is created when the job is completed.');

        return Storage::disk('local')->download($report->path, $report->original_name, ['Content-Type' => 'application/pdf']);
    }
}
