<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Building;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::TenantAdmin,
            'building_id' => null,
            'tenant_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Platform admin: sees across all buildings. */
    public function platformAdmin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::PlatformAdmin,
            'building_id' => null,
            'tenant_id' => null,
        ]);
    }

    /** Building manager scoped to a building. */
    public function buildingManager(Building $building): static
    {
        return $this->state(fn () => [
            'role' => UserRole::BuildingManager,
            'building_id' => $building->id,
            'tenant_id' => null,
        ]);
    }

    /** Tenant admin scoped to a single tenant (and its building). */
    public function tenantAdmin(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'role' => UserRole::TenantAdmin,
            'building_id' => $tenant->building_id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
