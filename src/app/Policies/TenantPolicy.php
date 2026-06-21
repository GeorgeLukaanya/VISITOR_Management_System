<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isBuildingManager()) {
            return $tenant->building_id === $user->building_id;
        }

        if ($user->isTenantAdmin()) {
            return $tenant->id === $user->tenant_id;
        }

        return false;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        // Tenants manage their own notification settings; managers manage any in-building.
        return $this->view($user, $tenant)
            && ($user->isPlatformAdmin() || $user->isBuildingManager() || $user->isTenantAdmin());
    }
}
