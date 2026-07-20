<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Hooks\Settings;

/**
 * Regression coverage for the "Missing view." settings bug.
 *
 * PluginManager::hooksFor() calls app()->call([hook, 'authorize'], ...)
 * which injects a fresh empty User from the container — not the
 * authenticated session user. Settings::authorize() must resolve
 * auth()->user() itself; otherwise the hook is filtered out and
 * PluginSettingsController falls back to the 'plugins.missing' view.
 *
 * The auth() shim and Illuminate\Foundation\Auth\User stub live in
 * tests/bootstrap.php so they load before Settings is autoloaded.
 */
class SettingsAuthorizeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__test_auth_resolver'] = null;
    }

    public function test_authorize_returns_true_for_authenticated_admin(): void
    {
        $GLOBALS['__test_auth_resolver'] = fn () => new \Illuminate\Foundation\Auth\User(['role' => 'admin']);

        $hook = new Settings();
        $injectedUser = new \Illuminate\Foundation\Auth\User([]); // empty, like the container gives

        $this->assertTrue($hook->authorize($injectedUser));
    }

    public function test_authorize_prefers_authenticated_user_over_injected_container_user(): void
    {
        // The injected user is NOT admin; the authenticated user IS admin.
        // authorize() must return true (use auth()->user(), not the injected one).
        $GLOBALS['__test_auth_resolver'] = fn () => new \Illuminate\Foundation\Auth\User(['role' => 'admin']);

        $hook = new Settings();
        $injectedUser = new \Illuminate\Foundation\Auth\User(['role' => 'regular']);

        $this->assertTrue($hook->authorize($injectedUser));
    }

    public function test_authorize_returns_false_when_no_authenticated_user(): void
    {
        $GLOBALS['__test_auth_resolver'] = fn () => null;

        $hook = new Settings();
        $injectedUser = new \Illuminate\Foundation\Auth\User([]); // empty

        $this->assertFalse($hook->authorize($injectedUser));
    }

    public function test_authorize_returns_false_for_non_admin_authenticated_user(): void
    {
        $GLOBALS['__test_auth_resolver'] = fn () => new \Illuminate\Foundation\Auth\User(['role' => 'regular']);

        $hook = new Settings();
        $injectedUser = new \Illuminate\Foundation\Auth\User([]);

        $this->assertFalse($hook->authorize($injectedUser));
    }

    protected function tearDown(): void
    {
        $GLOBALS['__test_auth_resolver'] = null;
    }
}
