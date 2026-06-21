<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind the base TestCase to Feature tests. Unit tests stay framework-free.
| RefreshDatabase is applied broadly because most of this app is data-driven
| (tenants, visits) and we want a clean, migrated schema per test.
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations & Helpers
|--------------------------------------------------------------------------
*/

/**
 * Build an Africa's Talking-shaped USSD callback payload for tests.
 * Mirrors the fields AT POSTs on each step (see CLAUDE.md USSD section).
 */
function ussdPayload(array $overrides = []): array
{
    return array_merge([
        'sessionId' => 'test-session-'.uniqid(),
        'phoneNumber' => '+256700000001',
        'serviceCode' => '*384*1234#',
        'networkCode' => '62001',
        'text' => '',
    ], $overrides);
}
