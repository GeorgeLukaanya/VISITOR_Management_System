<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' House',
            'address' => fake()->streetAddress().', Kampala',
            'manager_name' => fake()->name(),
            'manager_phone' => '+25670'.fake()->numerify('#######'),
        ];
    }
}
