<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Tenant;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Filled from the tenant in configure() so the two FKs always agree.
            'building_id' => null,
            'visitor_phone' => '+25670'.fake()->numerify('#######'),
            'purpose' => fake()->randomElement(['Meeting', 'Delivery', 'Interview', 'Other']),
            'status' => VisitStatus::CheckedIn,
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'ussd_session_id' => 'ATUssd_'.Str::random(20),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Visit $visit) {
            // Keep building_id consistent with the tenant unless explicitly set.
            if ($visit->building_id === null && $visit->tenant_id !== null) {
                $visit->building_id = Tenant::withoutGlobalScopes()
                    ->whereKey($visit->tenant_id)
                    ->value('building_id');
            }
        });
    }

    /** Force the visit onto an existing tenant (keeps building_id consistent). */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->id,
            'building_id' => $tenant->building_id,
        ]);
    }
}
