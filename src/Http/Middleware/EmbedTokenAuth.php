<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Middleware\SubstituteBindings;

/**
 * Allows map read endpoints to be consumed by external systems
 * (NOC dashboards, Grafana, scripts) without a browser session.
 *
 * A request is accepted when it carries a valid embed token:
 *   Authorization: Bearer <token>   (preferred)
 *   ?token=<token>                  (for <img>/iframe URLs)
 *
 * Without a token, use the complete LibreNMS web/auth middleware stack.
 * Explicit tokens require weathermapng.security.embed_token and are
 * compared in constant time; they never fall back to session auth.
 *
 * Routes using this middleware must NOT sit in LibreNMS's 'web'
 * middleware group: that group includes session-user middleware
 * (VerifyUserEnabled, LoadUserPreferences). Token requests only need
 * route bindings. Register each URL once with this middleware only.
 */
class EmbedTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $presented = $this->extractToken($request);
        if ($presented === null) {
            return app(Pipeline::class)->send($request)
                ->through(app('router')->resolveMiddleware(['web', 'auth']))
                ->then($next);
        }

        $configured = config('weathermapng.security.embed_token');

        if (empty($configured)) {
            abort(403, 'Embed token auth is not configured.');
        }

        if (!hash_equals((string) $configured, $presented)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing embed token.',
            ], 401);
        }

        $request->attributes->set('weathermapng.embed_token', $presented);
        $response = app(SubstituteBindings::class)->handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store, no-transform');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
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
