<?php

namespace App\Services\Microsoft;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The portal's app registration in Compass's Microsoft Entra ID. Gets app-only access tokens
 * (client credentials) for Microsoft Graph and Dataverse, cached until shortly before they expire.
 */
class MicrosoftApp
{
    public static function config(string $key): mixed
    {
        return config('services.microsoft.'.$key);
    }

    /** Tenant, client ID and secret are all set. */
    public static function isConfigured(): bool
    {
        return filled(self::config('tenant_id')) && filled(self::config('client_id')) && filled(self::config('client_secret'));
    }

    public static function authority(): string
    {
        return 'https://login.microsoftonline.com/'.self::config('tenant_id');
    }

    /** @param  string  $scope  e.g. https://graph.microsoft.com/.default */
    public static function token(string $scope): string
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Microsoft 365 is not set up: MICROSOFT_TENANT_ID, MICROSOFT_CLIENT_ID and MICROSOFT_CLIENT_SECRET are needed.');
        }
        $key = 'ms-token:'.sha1(self::config('tenant_id').'|'.self::config('client_id').'|'.$scope);

        if ($token = Cache::get($key)) {
            return $token;
        }

        $res = Http::asForm()->timeout(20)->post(self::authority().'/oauth2/v2.0/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::config('client_id'),
            'client_secret' => self::config('client_secret'),
            'scope' => $scope,
        ]);
        if (! $res->successful() || ! $res->json('access_token')) {
            throw new RuntimeException('Microsoft sign-in for the portal app failed: '.self::error($res->json()));
        }
        Cache::put($key, $res->json('access_token'), max(60, (int) $res->json('expires_in', 3600) - 300));

        return $res->json('access_token');
    }

    /** A short readable message from a Microsoft error response. */
    public static function error(?array $json): string
    {
        $msg = $json['error_description'] ?? $json['error']['message'] ?? $json['error'] ?? 'no details';
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        return strtok((string) $msg, "\r\n");
    }
}
