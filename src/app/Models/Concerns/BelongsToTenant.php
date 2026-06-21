<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;

/**
 * Applies row-level tenant scoping to a model.
 *
 * A model using this trait declares which of its columns are the tenant and/or
 * building anchors by overriding tenantColumn()/buildingColumn() if they differ
 * from the defaults below. The global TenantScope reads those to constrain
 * queries to the authenticated dashboard user's tenant/building.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /** Column holding the owning tenant id, or null if the model is building-level only. */
    public function tenantColumn(): ?string
    {
        return 'tenant_id';
    }

    /** Column holding the owning building id, or null if not building-scoped. */
    public function buildingColumn(): ?string
    {
        return 'building_id';
    }
}
