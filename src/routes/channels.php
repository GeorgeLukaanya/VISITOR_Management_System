<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Guard-tablet arrival feed, one private channel per building.
 *
 * Authorized for the building's manager and platform admins. NOTE: a dedicated
 * guard-tablet device auth (token -> Guard) is not defined in Phase 1; for now
 * the tablet authenticates as a dashboard user. Revisit when the tablet's auth
 * model is specced.
 */
Broadcast::channel('guards.building.{buildingId}', function (User $user, int $buildingId) {
    return $user->isPlatformAdmin()
        || ($user->isBuildingManager() && $user->building_id === $buildingId);
});
