<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class BuildingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->isBuildingManager();
    }

    public function view(User $user, Building $building): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Managers (and their tenants) may only see their own building.
        return $building->id === $user->building_id;
    }
}
