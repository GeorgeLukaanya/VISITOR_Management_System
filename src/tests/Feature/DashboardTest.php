<?php

use App\Models\Building;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;

/**
 * Step 5: dashboards + CSV export, enforcing the Step 2 scoping. The key promise
 * (CLAUDE.md): a tenant export contains ONLY that tenant's rows.
 */

beforeEach(function () {
    $this->building = Building::factory()->create();
    $this->tenantA = Tenant::factory()->create(['building_id' => $this->building->id, 'name' => 'Acme']);
    $this->tenantB = Tenant::factory()->create(['building_id' => $this->building->id, 'name' => 'Umbrella']);

    $this->visitA = Visit::factory()->forTenant($this->tenantA)->create(['visitor_phone' => '+256700000AAA']);
    $this->visitB = Visit::factory()->forTenant($this->tenantB)->create(['visitor_phone' => '+256700000BBB']);
});

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('lets a user log in and reach the dashboard', function () {
    $user = User::factory()->tenantAdmin($this->tenantA)->create(['password' => bcrypt('secret123')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('shows a tenant admin only their own visits on the dashboard', function () {
    $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('+256700000AAA')
        ->assertDontSee('+256700000BBB');
});

it('exports only the tenant\'s own rows to CSV', function () {
    $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

    $csv = $this->get(route('dashboard.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
        ->streamedContent();

    expect($csv)
        ->toContain('+256700000AAA')
        ->not->toContain('+256700000BBB');
});

it('lets a building manager see and export all visits in their building', function () {
    $this->actingAs(User::factory()->buildingManager($this->building)->create());

    $csv = $this->get(route('dashboard.export'))->assertOk()->streamedContent();

    expect($csv)
        ->toContain('+256700000AAA')
        ->toContain('+256700000BBB');
});

it('never leaks visits from another building to a building manager', function () {
    $otherBuilding = Building::factory()->create();
    $otherTenant = Tenant::factory()->create(['building_id' => $otherBuilding->id]);
    Visit::factory()->forTenant($otherTenant)->create(['visitor_phone' => '+256700000ZZZ']);

    $this->actingAs(User::factory()->buildingManager($this->building)->create());

    $csv = $this->get(route('dashboard.export'))->streamedContent();

    expect($csv)->not->toContain('+256700000ZZZ');
});
