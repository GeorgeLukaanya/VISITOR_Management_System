<?php

namespace App\Models\Scopes;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Row-level multi-tenancy (see CLAUDE.md "Multi-tenancy model").
 *
 * Constrains every query on a scoped model to what the *currently authenticated
 * dashboard user* is allowed to see:
 *
 *   - tenant_admin    -> only rows for their tenant_id
 *   - building_manager-> only rows for their building_id
 *   - platform_admin  -> everything (no constraint)
 *
 * When there is NO authenticated user (the USSD callback, queue workers, the
 * seeder, console commands) the scope is a no-op: those contexts legitimately
 * operate across tenants. Authorization for write paths is handled explicitly,
 * not by this read scope.
 *
 * Models opt in via the BelongsToTenant trait and must expose the column names
 * through tenantColumn()/buildingColumn().
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null) {
            return; // USSD / queue / console / seeder — intentionally unscoped.
        }

        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        if ($role === UserRole::PlatformAdmin) {
            return; // Sees across all buildings.
        }

        if ($role === UserRole::BuildingManager && $model->buildingColumn()) {
            $builder->where($model->qualifyColumn($model->buildingColumn()), $user->building_id);

            return;
        }

        if ($role === UserRole::TenantAdmin && $model->tenantColumn()) {
            $builder->where($model->qualifyColumn($model->tenantColumn()), $user->tenant_id);

            return;
        }

        // Unknown/ misconfigured role: fail closed — show nothing rather than leak.
        $builder->whereRaw('1 = 0');
    }
}
