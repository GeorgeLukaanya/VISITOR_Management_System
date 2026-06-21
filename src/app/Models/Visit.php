<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single visitor check-in — the digital replacement for one line in the
 * paper visitor's book. Carries PII (visitor_phone); never exposed cross-tenant
 * (enforced by the BelongsToTenant global scope + VisitPolicy).
 */
class Visit extends Model
{
    /** @use HasFactory<\Database\Factories\VisitFactory> */
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'building_id',
        'tenant_id',
        'visitor_phone',
        'purpose',
        'status',
        'checked_in_at',
        'checked_out_at',
        'ussd_session_id',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
