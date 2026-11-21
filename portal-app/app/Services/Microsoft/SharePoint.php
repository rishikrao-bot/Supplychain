<?php

namespace App\Services\Microsoft;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Files in a SharePoint document library, through Microsoft Graph with the portal's own
 * app permission (Sites.Selected: only the sites you have granted it).
 */
class SharePoint
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public static function isConfigured(): bool
    {
        return MicrosoftApp::isConfigured() && filled(MicrosoftApp::config('sharepoint_site'));
    }

    private static function http(): PendingRequest
    {
        return Http::withToken(MicrosoftApp::token('https://graph.microsoft.com/.default'))->acceptJson()->timeout(60);
    }

    private static function get(string $url): array
    {
        $res = self::http()->get(self::GRAPH.$url);
        if (! $res->successful()) {
            throw new RuntimeException('SharePoint: '.MicrosoftApp::error($res->json())." ({$res->status()})");
        }

        return $res->json();
    }

    /** The configured site: id, name and web address. */
    public static function site(): array
    {
        $site = trim((string) MicrosoftApp::config('sharepoint_site'));

        return Cache::remember('sp-site:'.$site, 3600, fn () => self::get('/sites/'.$site));
    }

    /** The configured document library (drive) in the site. */
    public static function library(): array
    {
        $site = self::site();
        $name = MicrosoftApp::config('sharepoint_library') ?: 'Documents';

        return Cache::remember('sp-drive:'.$site['id'].':'.$name, 3600, function () use ($site, $name) {
            $drives = self::get('/sites/'.$site['id'].'/drives?$select=id,name,webUrl')['value'] ?? [];
            foreach ($drives as $d) {
                if (strcasecmp($d['name'], $name) === 0 || ($name === 'Documents' && strcasecmp($d['name'], 'Shared Documents') === 0)) {
                    return $d;
                }
            }
            throw new RuntimeException("SharePoint: no document library called \"{$name}\" in {$site['webUrl']}. Found: ".implode(', ', array_column($drives, 'name')));
        });
    }

    /**
     * Upload (or replace) a file. $folder is a path inside the library, e.g. "Portal/Completion reports/Client Co";
     * missing folders are created. Returns the SharePoint item: id, name, webUrl.
     */
    public static function upload(string $folder, string $filename, string $contents, string $mime = 'application/octet-stream'): array
    {
        $drive = self::library();
        $parts = array_filter(array_map([self::class, 'cleanName'], explode('/', $folder.'/'.$filename)), 'strlen');
        $path = implode('/', array_map('rawurlencode', $parts));

        $url = self::GRAPH.'/drives/'.$drive['id'].'/root:/'.$path.':/content';
        if (strlen($contents) <= 4 * 1024 * 1024) {
            $res = self::http()->withBody($contents, $mime)->put($url);
        } else {
            $res = self::uploadLarge($drive['id'], $path, $contents);
        }
        if (! $res->successful()) {
            throw new RuntimeException('SharePoint upload failed: '.MicrosoftApp::error($res->json())." ({$res->status()})");
        }

        return ['id' => $res->json('id'), 'name' => $res->json('name'), 'webUrl' => $res->json('webUrl')];
    }

    /** Files over 4 MB go up in chunks through an upload session. */
    private static function uploadLarge(string $driveId, string $path, string $contents)
    {
        $session = self::http()->post(self::GRAPH.'/drives/'.$driveId.'/root:/'.$path.':/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);
        if (! $session->successful()) {
            return $session;
        }
        $total = strlen($contents);
        $chunk = 320 * 1024 * 10; // multiple of 320 KiB, as Graph requires
        for ($offset = 0; $offset < $total; $offset += $chunk) {
            $part = substr($contents, $offset, $chunk);
            $end = $offset + strlen($part) - 1;
            // The upload URL is pre-authorised: no bearer token on these requests.
            $res = Http::timeout(120)->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$total}"])
                ->withBody($part, 'application/octet-stream')->put($session->json('uploadUrl'));
            if (! $res->successful()) {
                return $res;
            }
        }

        return $res;
    }

    /** SharePoint doesn't allow " * : < > ? / \ | in names, or leading/trailing spaces and dots. */
    public static function cleanName(string $name): string
    {
        $name = preg_replace('/["*:<>?\/\\\\|#%]+/', '-', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name, " .\t");
    }
}
