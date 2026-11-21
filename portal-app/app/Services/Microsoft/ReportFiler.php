<?php

namespace App\Services\Microsoft;

use App\Models\Attachment;
use App\Models\WorkOrder;
use App\Services\Activity;
use App\Services\CompletionReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copies completion reports into SharePoint:
 *   <SHAREPOINT_FOLDER>/<Client>/<Contract>/P-1001-completion-report.pdf
 * The portal keeps its own copy, so a SharePoint problem never blocks a job. Failed copies are
 * retried by `php artisan portal:sharepoint-sync` (run by the scheduler every 15 minutes).
 */
class ReportFiler
{
    public static function folderFor(WorkOrder $wo): string
    {
        $wo->loadMissing(['client', 'contract']);

        return implode('/', array_filter([
            MicrosoftApp::config('sharepoint_folder') ?: 'Portal/Completion reports',
            $wo->client?->name,
            $wo->contract?->name,
        ]));
    }

    /** Upload one report. Returns true when it is in SharePoint. Never throws. */
    public static function file(Attachment $report): bool
    {
        if (! SharePoint::isConfigured() || $report->kind !== CompletionReport::KIND) {
            return false;
        }
        $wo = $report->attachable;
        if (! $wo instanceof WorkOrder) {
            return false;
        }
        $meta = $report->meta ?? [];
        try {
            $item = SharePoint::upload(self::folderFor($wo), $report->original_name, Storage::disk('local')->get($report->path), 'application/pdf');
            unset($meta['sharepoint_error']);
            $meta['sharepoint'] = $item + ['uploaded_at' => now()->toIso8601String()];
            $report->forceFill(['meta' => $meta])->save();
            Activity::log('sharepoint.upload', "Completion report for {$wo->ref()} saved to SharePoint.", $wo, ['url' => $item['webUrl']], $wo->account_id);

            return true;
        } catch (Throwable $e) {
            $meta['sharepoint_error'] = ['message' => $e->getMessage(), 'at' => now()->toIso8601String(), 'tries' => ($meta['sharepoint_error']['tries'] ?? 0) + 1];
            $report->forceFill(['meta' => $meta])->save();
            Log::warning('SharePoint upload failed for '.$wo->ref().': '.$e->getMessage());

            return false;
        }
    }

    /** Reports not yet in SharePoint (newest first). */
    public static function pending(int $limit = 50)
    {
        return Attachment::where('kind', CompletionReport::KIND)
            ->where('attachable_type', (new WorkOrder)->getMorphClass())
            ->latest('id')->limit(500)->get()
            ->filter(fn ($a) => empty($a->meta['sharepoint']))
            ->take($limit);
    }
}
