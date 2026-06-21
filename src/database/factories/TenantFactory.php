<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => fake()->company(),
            // Short, human-typable, globally unique routing code.
            'routing_code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'contact_name' => fake()->name(),
            'contact_phone' => '+25670'.fake()->numerify('#######'),
            'notify_tenant' => true,
            'notify_guard' => true,
        ];
    }

    public function withCode(string $code): static
    {
        return $this->state(fn () => ['routing_code' => $code]);
    }
}
