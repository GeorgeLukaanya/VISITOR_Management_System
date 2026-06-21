<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Guard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Minimum demo dataset for Phase 1 (see CLAUDE.md "Local development & testing"):
 * one building, two tenants with distinct routing codes, and one guard — plus a
 * dashboard user per role so the three scoping levels can be exercised by hand.
 *
 * Login password for all seeded users: "password".
 *
 * NOTE: this is dev/demo data only. Per the Uganda DPA constraints, the sandbox
 * must never hold real visitor data.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Idempotent: reuse an existing demo row if present, otherwise create it.
        // This lets `make seed` be re-run safely against an already-seeded
        // database without hitting unique-constraint violations. Each lookup is
        // keyed on the row's natural unique field (building name, routing code,
        // guard phone, user email).
        $building = Building::firstWhere('name', 'Crested Towers')
            ?? Building::factory()->create([
                'name' => 'Crested Towers',
                'address' => 'Hannington Road, Kampala',
                'manager_name' => 'Building Manager',
                'manager_phone' => '+256700000100',
            ]);

        $acme = Tenant::firstWhere('routing_code', '1001')
            ?? Tenant::factory()->withCode('1001')->create([
                'building_id' => $building->id,
                'name' => 'Acme Bank',
                'contact_name' => 'Acme Reception',
                'contact_phone' => '+256700000201',
            ]);

        $umbrella = Tenant::firstWhere('routing_code', '1002')
            ?? Tenant::factory()->withCode('1002')->create([
                'building_id' => $building->id,
                'name' => 'Umbrella Legal',
                'contact_name' => 'Umbrella Front Desk',
                'contact_phone' => '+256700000202',
            ]);

        Guard::firstWhere('phone', '+256700000300')
            ?? Guard::factory()->create([
                'building_id' => $building->id,
                'name' => 'Front Gate Guard',
                'phone' => '+256700000300',
            ]);

        // Dashboard users — one per role.
        User::firstWhere('email', 'admin@example.com')
            ?? User::factory()->platformAdmin()->create([
                'name' => 'Platform Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
            ]);

        User::firstWhere('email', 'manager@example.com')
            ?? User::factory()->buildingManager($building)->create([
                'name' => 'Manager — Crested Towers',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
            ]);

        User::firstWhere('email', 'acme@example.com')
            ?? User::factory()->tenantAdmin($acme)->create([
                'name' => 'Admin — Acme Bank',
                'email' => 'acme@example.com',
                'password' => Hash::make('password'),
            ]);

        User::firstWhere('email', 'umbrella@example.com')
            ?? User::factory()->tenantAdmin($umbrella)->create([
                'name' => 'Admin — Umbrella Legal',
                'email' => 'umbrella@example.com',
                'password' => Hash::make('password'),
            ]);

        $this->command?->info('Seeded: 1 building, 2 tenants (codes 1001/1002), 1 guard, 4 users.');
        $this->command?->info('Tenant codes — Acme Bank: 1001, Umbrella Legal: 1002');
    }
}
