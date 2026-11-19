<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the branded PDF completion report for a job and attaches it to the job.
 * No prices are shown, so the report can go straight to the client.
 */
class CompletionReport
{
    public const KIND = 'completion_report';

    public static function generate(WorkOrder $wo): Attachment
    {
        $wo->loadMissing(['client', 'contract', 'site', 'trades.competency', 'visits.materials', 'visits.attachments', 'visits.user', 'exceptions', 'children', 'quotes']);
        $disk = Storage::disk('local');
        $img = function (?string $path) use ($disk) {
            if (! $path || ! $disk->exists($path)) {
                return null;
            }
            $mime = $disk->mimeType($path) ?: 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
        };
        $logo = public_path('img/compass-mep-logo.png');
        $html = view('pdf.completion-report', [
            'wo' => $wo,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
            'img' => $img,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        // Page numbers ("Page 1 of 2") are drawn after layout, when the page count is known.
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 40, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.345, 0.439, 0.471]);
        $name = $wo->ref().'-completion-report.pdf';
        $path = 'reports/'.$wo->account_id.'/'.$name;
        $disk->put($path, $pdf->output());

        // One current report per job: replace any earlier version.
        $wo->attachments()->where('kind', self::KIND)->get()->each(fn ($a) => $a->delete());

        return $wo->attachments()->create([
            'account_id' => $wo->account_id,
            'kind' => self::KIND,
            'path' => $path,
            'original_name' => $name,
            'mime' => 'application/pdf',
            'size' => $disk->size($path),
            'meta' => ['generated_at' => now()->toIso8601String()],
        ]);
    }
}
