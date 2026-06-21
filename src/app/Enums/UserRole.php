<?php

namespace App\Enums;

/**
 * Who a dashboard user is, and therefore what the tenant scope lets them see.
 *
 *  - PlatformAdmin   : us. Sees across all buildings (no scope constraint).
 *  - BuildingManager : sees every tenant/visit within their own building.
 *  - TenantAdmin     : sees only their own tenant's visits (never cross-tenant).
 */
enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case BuildingManager = 'building_manager';
    case TenantAdmin = 'tenant_admin';
}
