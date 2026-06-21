<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    /** @use HasFactory<\Database\Factories\BuildingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'manager_name',
        'manager_phone',
    ];

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /** @return HasMany<Guard, $this> */
    public function guards(): HasMany
    {
        return $this->hasMany(Guard::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
