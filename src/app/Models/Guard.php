<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guard extends Model
{
    /** @use HasFactory<\Database\Factories\GuardFactory> */
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'building_id',
        'name',
        'phone',
        'device_token',
    ];

    /** Guards are building-level, not owned by a single tenant. */
    public function tenantColumn(): ?string
    {
        return null;
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
