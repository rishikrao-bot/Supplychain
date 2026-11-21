<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The portal's data API: a key created by a Compass admin (Microsoft 365 page), sent as
 * "Authorization: Bearer cmp_…", "X-Api-Key: cmp_…" or ?key=cmp_… (Power BI's Web API credential).
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->bearerToken() ?: $request->header('X-Api-Key') ?: $request->query('key');
        $key = ApiKey::findActive(is_string($secret) ? $secret : null);
        if (! $key) {
            return response()->json(['error' => 'Missing or revoked API key.'], 401);
        }
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinutes(10))) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
