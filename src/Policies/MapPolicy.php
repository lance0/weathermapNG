<?php

namespace LibreNMS\Plugins\WeathermapNG\Policies;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use Illuminate\Support\Facades\Auth;

class MapPolicy
{
    /**
     * Determine whether the user can view any maps.
     */
    public function viewAny($user): bool
    {
        // All authenticated users can view maps
        return Auth::check();
    }

    /**
     * Determine whether the user can view the map.
     */
    public function view($user, Map $map): bool
    {
        return Auth::check();
    }

    /**
     * Determine whether the user can create maps.
     */
    public function create($user): bool
    {
        // Only allow users with appropriate permissions
        // This could be extended to check for specific roles/permissions
        return Auth::check() && $this->hasMapPermission($user);
    }

    /**
     * Determine whether the user can update the map.
     */
    public function update($user, Map $map): bool
    {
        return Auth::check() && $this->hasMapPermission($user);
    }

    /**
     * Determine whether the user can delete the map.
     */
    public function delete($user, Map $map): bool
    {
        // Could add ownership check here
        return Auth::check() && $this->hasMapPermission($user);
    }

    /**
     * Determine whether the user can restore the map.
     */
    public function restore($user, Map $map): bool
    {
        return Auth::check() && $this->hasMapPermission($user);
    }

    /**
     * Determine whether the user can permanently delete the map.
     */
    public function forceDelete($user, Map $map): bool
    {
        return Auth::check() && $this->hasMapPermission($user);
    }

    /**
     * Check if user has permission to manage maps
     */
    private function hasMapPermission($user): bool
    {
        // Basic implementation - could be extended to check:
        // - User roles/permissions
        // - Group membership
        // - Specific map ownership

        if (!$user) {
            return false;
        }

        // For now, allow all authenticated users
        // In production, you might want to check:
        // return $user->hasRole('admin') || $user->hasPermission('manage_weathermaps');

        return true;
    }

    /**
     * Check if user can export maps
     */
    public function export($user, Map $map): bool
    {
        return Auth::check();
    }

    /**
     * Check if user can import maps
     */
    public function import($user): bool
    {
        return Auth::check() && $this->hasMapPermission($user);
    }

    /**
     * Check if user can embed maps
     */
    public function embed($user, Map $map): bool
    {
        // Allow embedding for public maps or with permission
        return Auth::check();
    }
}
