<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, BelongsToTenant;

    /**
     * A tenant_admin's own tenant is identified by this row's primary key, so
     * the tenant anchor here is `id` (not a `tenant_id` column).
     */
    public function tenantColumn(): ?string
    {
        return 'id';
    }

    protected $fillable = [
        'building_id',
        'name',
        'routing_code',
        'contact_name',
        'contact_phone',
        'notify_tenant',
        'notify_guard',
    ];

    protected function casts(): array
    {
        return [
            'notify_tenant' => 'boolean',
            'notify_guard' => 'boolean',
        ];
    }

    /**
     * Resolve a tenant from a routing code typed by a visitor on the USSD flow.
     *
     * Phase 1 uses globally-unique codes (see the create_tenants_table migration
     * for the decision + Arthur flag), so a code maps to exactly one tenant.
     * Matching is case-insensitive and whitespace-trimmed to be forgiving of
     * how codes are posted at reception.
     */
    public static function resolveByCode(string $code): ?self
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(routing_code) = ?', [mb_strtolower($code)])
            ->first();
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
