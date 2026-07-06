<?php

namespace LibreNMS\Plugins\WeathermapNG;

/**
 * Shared admin-gate logic for controllers and hooks.
 * Mirrors the checks in Hooks\Settings::authorize() so the same
 * admin-detection logic is used everywhere.
 */
trait AdminCheck
{
    protected function isAdmin(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasGlobalAdmin') && $user->hasGlobalAdmin()) {
            return true;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (isset($user->level) && $user->level >= 10) {
            return true;
        }

        return false;
    }

    /**
     * Abort with 403 if the authenticated user is not an admin.
     */
    protected function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            abort(403, 'This action requires admin privileges.');
        }
    }
}
