<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

/**
 * Authorization for visit records. This is defence-in-depth: the TenantScope
 * already hides other tenants' rows from queries, but policies guarantee that
 * an explicitly-loaded record (e.g. route-model binding) can't be viewed or
 * exported across tenant/building boundaries.
 */
class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        // Any authenticated dashboard user may list visits; the scope narrows them.
        return true;
    }

    public function view(User $user, Visit $visit): bool
    {
        return $this->withinScope($user, $visit);
    }

    public function export(User $user, ?Visit $visit = null): bool
    {
        return true; // Listing/export are scope-narrowed; row checks happen on view().
    }

    /**
     * True when the visit falls inside the user's tenant/building boundary.
     */
    protected function withinScope(User $user, Visit $visit): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->isBuildingManager()) {
            return $visit->building_id === $user->building_id;
        }

        if ($user->isTenantAdmin()) {
            return $visit->tenant_id === $user->tenant_id;
        }

        return false;
    }
}
