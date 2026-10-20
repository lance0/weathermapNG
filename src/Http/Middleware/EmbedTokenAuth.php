<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Allows map read endpoints to be consumed by external systems
 * (NOC dashboards, Grafana, scripts) without a browser session.
 *
 * A request is accepted when it carries a valid embed token:
 *   Authorization: Bearer <token>   (preferred)
 *   ?token=<token>                  (for <img>/iframe URLs)
 *
 * The token is compared in constant time against the configured
 * weathermapng.security.embed_token. When no token is configured, this
 * middleware rejects — routes using it must stay session-gated
 * (register them in the auth group instead, or both).
 *
 * Routes using this middleware must NOT sit in LibreNMS's 'web'
 * middleware group: that group includes session-user middleware
 * (VerifyUserEnabled, LoadUserPreferences) that rejects anonymous
 * requests before token auth runs. Register with this middleware only.
 */
class EmbedTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $configured = config('weathermapng.security.embed_token');

        if (empty($configured)) {
            // Token auth not configured — deny. Operators who want
            // token-gated embeds must set WEATHERMAPNG_EMBED_TOKEN;
            // otherwise the session-gated routes remain the only path.
            abort(403, 'Embed token auth is not configured.');
        }

        $presented = $this->extractToken($request);

        if ($presented === null || !hash_equals((string) $configured, $presented)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing embed token.',
            ], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        return self::tokenFrom($request->headers->get('Authorization'), $request->query('token'));
    }

    /**
     * Pure token extraction: Authorization: Bearer wins, then ?token=.
     * Static and request-free so it is unit-testable outside Laravel.
     */
    public static function tokenFrom(?string $authorization, mixed $queryToken): ?string
    {
        if ($authorization && preg_match('/^Bearer\s+(.+)$/i', $authorization, $m)) {
            return trim($m[1]);
        }

        return is_string($queryToken) && $queryToken !== '' ? $queryToken : null;
    }
}
