<?php

use App\Models\Building;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;

/**
 * Proves the row-level multi-tenancy promise from CLAUDE.md:
 * a tenant can never read another tenant's visits, even by guessing IDs.
 */

beforeEach(function () {
    $this->building = Building::factory()->create();
    $this->tenantA = Tenant::factory()->withCode('AAA1')->create(['building_id' => $this->building->id]);
    $this->tenantB = Tenant::factory()->withCode('BBB2')->create(['building_id' => $this->building->id]);

    $this->visitA = Visit::factory()->forTenant($this->tenantA)->create();
    $this->visitB = Visit::factory()->forTenant($this->tenantB)->create();
});

it('shows a tenant admin only their own visits', function () {
    $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

    $visits = Visit::all();

    expect($visits)->toHaveCount(1)
        ->and($visits->first()->is($this->visitA))->toBeTrue();
});

it('hides another tenant\'s visit even when fetched by id', function () {
    $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

    expect(Visit::find($this->visitB->id))->toBeNull();
});

it('denies viewing another tenant\'s visit via policy', function () {
    $userA = User::factory()->tenantAdmin($this->tenantA)->create();

    // Fetch the row past the scope to simulate a guessed-id / route binding.
    $foreignVisit = Visit::withoutGlobalScopes()->find($this->visitB->id);

    expect($userA->can('view', $foreignVisit))->toBeFalse()
        ->and($userA->can('view', $this->visitA))->toBeTrue();
});

it('lets a building manager see every visit in their building', function () {
    $this->actingAs(User::factory()->buildingManager($this->building)->create());

    expect(Visit::all())->toHaveCount(2);
});

it('hides visits from other buildings from a building manager', function () {
    $otherBuilding = Building::factory()->create();
    $otherTenant = Tenant::factory()->create(['building_id' => $otherBuilding->id]);
    Visit::factory()->forTenant($otherTenant)->create();

    $this->actingAs(User::factory()->buildingManager($this->building)->create());

    expect(Visit::all())->toHaveCount(2); // only this building's two visits
});

it('lets a platform admin see all visits across buildings', function () {
    $otherBuilding = Building::factory()->create();
    $otherTenant = Tenant::factory()->create(['building_id' => $otherBuilding->id]);
    Visit::factory()->forTenant($otherTenant)->create();

    $this->actingAs(User::factory()->platformAdmin()->create());

    expect(Visit::all())->toHaveCount(3);
});

it('applies no scope when there is no authenticated user (USSD/queue context)', function () {
    // The USSD callback runs unauthenticated and must be able to write/read across tenants.
    expect(Visit::all())->toHaveCount(2);
});

it('scopes the tenant list itself to a tenant admin', function () {
    $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

    $tenants = Tenant::all();

    expect($tenants)->toHaveCount(1)
        ->and($tenants->first()->is($this->tenantA))->toBeTrue();
});
