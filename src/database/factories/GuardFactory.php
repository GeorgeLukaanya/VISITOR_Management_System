<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Guard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guard>
 */
class GuardFactory extends Factory
{
    protected $model = Guard::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => fake()->name(),
            'phone' => '+25670'.fake()->numerify('#######'),
            'device_token' => null,
        ];
    }
}
