<?php

use App\Models\Building;
use App\Models\Tenant;
use App\Models\Visit;

/**
 * Covers the DPA retention prune command (visits:prune). Old visits go, recent
 * ones stay, the window is overridable/disable-able, and --dry-run is a no-op.
 */
beforeEach(function () {
    $building = Building::factory()->create();
    $this->tenant = Tenant::factory()->create(['building_id' => $building->id]);
});

it('prunes visits older than the retention window and keeps recent ones', function () {
    config()->set('visits.retention_days', 180);

    $old = Visit::factory()->forTenant($this->tenant)->create([
        'checked_in_at' => now()->subDays(200),
    ]);
    $recent = Visit::factory()->forTenant($this->tenant)->create([
        'checked_in_at' => now()->subDays(10),
    ]);

    $this->artisan('visits:prune')->assertSuccessful();

    expect(Visit::withoutGlobalScopes()->find($old->id))->toBeNull();
    expect(Visit::withoutGlobalScopes()->find($recent->id))->not->toBeNull();
});

it('does nothing when retention is disabled (0 days)', function () {
    config()->set('visits.retention_days', 0);

    Visit::factory()->forTenant($this->tenant)->create([
        'checked_in_at' => now()->subDays(999),
    ]);

    $this->artisan('visits:prune')->assertSuccessful();

    expect(Visit::withoutGlobalScopes()->count())->toBe(1);
});

it('reports but deletes nothing on --dry-run', function () {
    config()->set('visits.retention_days', 30);

    Visit::factory()->forTenant($this->tenant)->create([
        'checked_in_at' => now()->subDays(100),
    ]);

    $this->artisan('visits:prune --dry-run')->assertSuccessful();

    expect(Visit::withoutGlobalScopes()->count())->toBe(1);
});

it('lets --days override the configured window', function () {
    config()->set('visits.retention_days', 365);

    $visit = Visit::factory()->forTenant($this->tenant)->create([
        'checked_in_at' => now()->subDays(100),
    ]);

    $this->artisan('visits:prune --days=30')->assertSuccessful();

    expect(Visit::withoutGlobalScopes()->find($visit->id))->toBeNull();
});
