<?php

namespace App\Policies;

use App\Models\Guard;
use App\Models\User;

class GuardPolicy
{
    public function viewAny(User $user): bool
    {
        // Guards are building-level; tenant admins don't manage them.
        return $user->isPlatformAdmin() || $user->isBuildingManager();
    }

    public function view(User $user, Guard $guard): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isBuildingManager()) {
            return $guard->building_id === $user->building_id;
        }

        return false;
    }
}
