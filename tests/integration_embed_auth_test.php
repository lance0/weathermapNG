#!/usr/bin/env php
<?php
// Run in an installed LibreNMS container; exercises the real HTTP middleware and session cookie.
require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
});

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use Symfony\Component\HttpFoundation\StreamedResponse;

config(['librenms.install' => false, 'weathermapng.demo_mode' => true]);
$failures = 0;
function checkEmbed(bool $ok, string $message): void
{
    global $failures;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    $failures += $ok ? 0 : 1;
}

function embedRequest(string $uri, array $cookies = [], array $headers = [], string $method = 'GET')
{
    auth()->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $request = Request::create('http://localhost' . $uri, $method, [], $cookies);
    $request->headers->set('Accept', 'application/json');
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    return $response;
}

// Persist an ordinary Laravel login and encrypt its cookie exactly as EncryptCookies does.
function embedSessionCookie(User $user): array
{
    auth()->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $session = app('session')->driver();
    $session->start();
    auth()->guard('web')->login($user);
    $session->save();
    $name = config('session.cookie');
    $encrypter = app('encrypter');
    return [$name => $encrypter->encrypt(
        CookieValuePrefix::create($name, $encrypter->getKey()) . $session->getId(), false
    )];
}

$map = null;
$user = null;
try {
    $tag = bin2hex(random_bytes(6));
    $user = new User(['username' => 'wmng-auth-' . $tag, 'auth_type' => 'mysql', 'enabled' => true]);
    $user->setPassword(bin2hex(random_bytes(16)));
    $user->save();
    $cookies = embedSessionCookie($user);
    $map = Map::create(['name' => 'wmng-auth-' . $tag, 'title' => 'Embed auth regression']);
    Node::create(['map_id' => $map->id, 'label' => 'Auth fixture', 'x' => 100, 'y' => 100]);
    $paths = [
        'embed' => '/plugin/WeathermapNG/embed/' . $map->id,
        'json' => '/plugin/WeathermapNG/api/maps/' . $map->id . '/json',
        'live' => '/plugin/WeathermapNG/api/maps/' . $map->id . '/live',
        'sse' => '/plugin/WeathermapNG/api/maps/' . $map->id . '/sse?interval=1&max=5',
    ];
    checkEmbed(embedRequest('/plugin/WeathermapNG/api/maps/' . $map->id . '/export', $cookies)->getStatusCode() === 200,
        'control: the session cookie authenticates on an existing session route');
    $token = 'test-token+/&=?#' . $tag;
    foreach ([null, $token] as $configured) {
        config(['weathermapng.security.embed_token' => $configured]);
        foreach ($paths as $name => $path) {
            $response = embedRequest($path, $cookies);
            checkEmbed($response->getStatusCode() === 200,
                "session $name with " . ($configured === null ? 'no' : 'configured') . " token: {$response->getStatusCode()}");
            if ($name === 'json' && $response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                checkEmbed(($data['id'] ?? null) === $map->id, 'session JSON resolves the requested map');
            }
            if ($name === 'embed' && $response->getStatusCode() === 200) {
                checkEmbed(str_contains($response->getContent(), 'embedToken: null'), 'session viewer does not disclose the configured token');
            }
            $anonymous = embedRequest($path);
            checkEmbed($anonymous->getStatusCode() === 401, "anonymous $name is denied: {$anonymous->getStatusCode()}");
        }
    }

    foreach (['Bearer' => ['Authorization' => 'Bearer ' . $token], 'query' => []] as $mode => $headers) {
        foreach ($paths as $name => $path) {
            $url = $path . ($mode === 'query' ? (str_contains($path, '?') ? '&' : '?') . 'token=' . rawurlencode($token) : '');
            $response = embedRequest($url, [], $headers);
            checkEmbed($response->getStatusCode() === 200, "$mode $name: {$response->getStatusCode()}");
            checkEmbed(str_contains($response->headers->get('Cache-Control', ''), 'no-store')
                && $response->headers->get('Referrer-Policy') === 'no-referrer', "$mode $name disables caching and referrers");
            if ($name === 'json' && $response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                checkEmbed(($data['id'] ?? null) === $map->id && count($data['nodes'] ?? []) === 1,
                    "$mode JSON binds the requested map and its node");
            }
            if ($name === 'sse' && $response instanceof StreamedResponse) {
                checkEmbed(str_starts_with($response->headers->get('Content-Type'), 'text/event-stream'), "$mode SSE is a stream");
            }
            if ($name === 'embed' && $response->getStatusCode() === 200) {
                checkEmbed(str_contains($response->getContent(), 'embedToken: ' . json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)),
                    "$mode embed passes only the authenticated token to the viewer");
            }
        }
    }

    foreach ($paths as $name => $path) {
        $bad = embedRequest($path, [], ['Authorization' => 'Bearer invalid']);
        checkEmbed($bad->getStatusCode() === 401, "invalid token $name is denied");
        $missing = preg_replace('~/\d+(?=/|\?|$)~', '/2147483647', $path);
        $response = embedRequest($missing, [], ['Authorization' => 'Bearer ' . $token]);
        checkEmbed($response->getStatusCode() === 404, "missing map $name returns 404: {$response->getStatusCode()}");
    }
    $response = embedRequest($paths['json'] . '?token=invalid', [], ['Authorization' => 'Bearer ' . $token]);
    checkEmbed($response->getStatusCode() === 200, 'Bearer takes precedence over query token');
    checkEmbed(embedRequest($paths['json'], $cookies, ['Authorization' => 'Bearer invalid'])->getStatusCode() === 401,
        'invalid explicit token cannot fall back to a valid session');
    checkEmbed(embedRequest($paths['json'] . '?token[]=invalid')->getStatusCode() === 401,
        'array query token is denied without a server error');
    $response = embedRequest('/plugin/WeathermapNG/api/maps/' . $map->id . '/save', [],
        ['Authorization' => 'Bearer ' . $token], 'POST');
    checkEmbed(in_array($response->getStatusCode(), [401, 419], true), 'embed token does not authorize map mutations');
    config(['weathermapng.security.embed_token' => null]);
    checkEmbed(embedRequest($paths['json'] . '?token=' . rawurlencode($token))->getStatusCode() === 403,
        'token access is denied when unconfigured');
    \App\Facades\LibrenmsConfig::set('twofactor', true);
    \App\Models\UserPref::setPref($user, 'twofactor', ['key' => 'test']);
    $twoFactor = embedRequest($paths['json'], $cookies);
    checkEmbed($twoFactor->getStatusCode() === 302 && str_ends_with($twoFactor->headers->get('Location', ''), '/2fa'),
        'session user must complete configured two-factor authentication');
    \App\Facades\LibrenmsConfig::set('twofactor', false);
    $user->enabled = false;
    $user->save();
    $disabled = embedRequest($paths['json'], $cookies);
    checkEmbed($disabled->getStatusCode() === 302 && str_ends_with($disabled->headers->get('Location', ''), '/login'),
        'disabled session user is logged out');
} finally {
    if ($map) {
        $map->nodes()->delete();
        $map->delete();
    }
    $user?->delete();
}
echo "$failures failure(s)\n";
exit($failures ? 1 : 0);
