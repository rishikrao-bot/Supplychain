<?php

namespace App\Services\Microsoft;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Graph as one person (delegated access): their own Outlook mail and To Do, nothing else.
 * The refresh token is saved (encrypted) when they sign in with Microsoft or click "Connect Outlook & To Do".
 */
class MicrosoftUser
{
    public const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** Delegated permissions the portal asks for. Admin consent for these is granted once in Entra (MICROSOFT.md). */
    public const SCOPES = 'openid profile email offline_access User.Read Mail.Read Tasks.ReadWrite';

    public static function graph(User $user): PendingRequest
    {
        return Http::withToken(self::token($user))->acceptJson()->timeout(30)->baseUrl(self::GRAPH);
    }

    public static function token(User $user): string
    {
        if (! $user->hasMicrosoft()) {
            throw new RuntimeException('Outlook and To Do are not connected for '.$user->name.'.');
        }
        $key = 'ms-user-token:'.$user->id;
        if ($token = Cache::get($key)) {
            return $token;
        }

        $res = Http::asForm()->timeout(20)->post(MicrosoftApp::authority().'/oauth2/v2.0/token', [
            'grant_type' => 'refresh_token',
            'client_id' => MicrosoftApp::config('client_id'),
            'client_secret' => MicrosoftApp::config('client_secret'),
            'refresh_token' => $user->microsoft_refresh_token,
            'scope' => self::SCOPES,
        ]);
        if (! $res->successful() || ! $res->json('access_token')) {
            if (in_array($res->json('error'), ['invalid_grant', 'interaction_required'], true)) {
                // Revoked, expired, password changed or account disabled: they need to connect again.
                self::disconnect($user);
            }
            throw new RuntimeException('Microsoft 365 connection for '.$user->name.' failed: '.MicrosoftApp::error($res->json()));
        }
        if ($res->json('refresh_token')) {
            $user->forceFill(['microsoft_refresh_token' => $res->json('refresh_token')])->saveQuietly();
        }
        Cache::put($key, $res->json('access_token'), max(60, (int) $res->json('expires_in', 3600) - 300));

        return $res->json('access_token');
    }

    public static function connect(User $user, string $refreshToken): void
    {
        $user->forceFill(['microsoft_refresh_token' => $refreshToken, 'microsoft_connected_at' => now()])->save();
        Cache::forget('ms-user-token:'.$user->id);
    }

    public static function disconnect(User $user): void
    {
        $user->forceFill(['microsoft_refresh_token' => null, 'microsoft_connected_at' => null])->saveQuietly();
        Cache::forget('ms-user-token:'.$user->id);
    }

    /** Throw a readable error for an unsuccessful Graph response. */
    public static function check($res, string $what): array
    {
        if (! $res->successful()) {
            throw new RuntimeException("{$what}: ".MicrosoftApp::error($res->json())." ({$res->status()})");
        }

        return $res->json() ?? [];
    }
}
