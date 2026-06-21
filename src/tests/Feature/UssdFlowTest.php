<?php

use App\Enums\VisitStatus;
use App\Jobs\NotifyVisitCheckedIn;
use App\Models\Building;
use App\Models\Tenant;
use App\Models\Visit;
use Illuminate\Support\Facades\Queue;

/**
 * Covers every transition of the two-screen USSD flow (CLAUDE.md "USSD flow").
 * Drives the real callback route with AT-shaped payloads.
 */

beforeEach(function () {
    Queue::fake();
    $this->building = Building::factory()->create();
    $this->tenant = Tenant::factory()->withCode('1001')->create([
        'building_id' => $this->building->id,
        'name' => 'Acme Bank',
    ]);
});

function postUssd(array $overrides = [])
{
    return test()->post('/ussd', ussdPayload($overrides));
}

it('screen 1: empty start asks for the tenant code', function () {
    $res = postUssd(['text' => '']);

    $res->assertOk();
    expect($res->getContent())->toBe('CON Enter the code of who you are visiting:');
});

it('screen 2: a valid code shows the tenant name and purpose menu', function () {
    $res = postUssd(['text' => '1001']);

    $res->assertOk();
    expect($res->getContent())
        ->toContain('CON You are visiting Acme Bank.')
        ->toContain('1. Meeting 2. Delivery 3. Interview 4. Other');
});

it('an invalid code ends the session and logs nothing', function () {
    $res = postUssd(['text' => '9999']);

    $res->assertOk();
    expect($res->getContent())->toBe('END Code not recognised. Please check the posted list.');
    expect(Visit::withoutGlobalScopes()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('a completed flow logs the visit and dispatches the notification', function () {
    $res = postUssd(['text' => '1001*1', 'phoneNumber' => '+256777123456']);

    $res->assertOk();
    expect($res->getContent())->toBe('END Thank you. Your arrival has been logged.');

    $visit = Visit::withoutGlobalScopes()->first();
    expect($visit)->not->toBeNull()
        ->and($visit->tenant_id)->toBe($this->tenant->id)
        ->and($visit->building_id)->toBe($this->building->id)
        ->and($visit->visitor_phone)->toBe('+256777123456') // taken from the session
        ->and($visit->purpose)->toBe('Meeting')
        ->and($visit->status)->toBe(VisitStatus::CheckedIn);

    Queue::assertPushed(NotifyVisitCheckedIn::class, 1);
});

it('resumes mid-flow using only the accumulated text (stateless)', function () {
    // Step 1: start
    expect(postUssd(['sessionId' => 'S1', 'text' => ''])->getContent())
        ->toStartWith('CON Enter the code');

    // Step 2: same session, code entered
    expect(postUssd(['sessionId' => 'S1', 'text' => '1001'])->getContent())
        ->toContain('You are visiting Acme Bank');

    // Step 3: same session, purpose appended
    $final = postUssd(['sessionId' => 'S1', 'text' => '1001*3']);
    expect($final->getContent())->toBe('END Thank you. Your arrival has been logged.');

    $visit = Visit::withoutGlobalScopes()->first();
    expect($visit->purpose)->toBe('Interview');
});

it('an out-of-range purpose re-shows the menu without adding a screen', function () {
    $res = postUssd(['text' => '1001*9']);

    $res->assertOk();
    expect($res->getContent())
        ->toStartWith('CON Invalid choice.')
        ->toContain('1. Meeting 2. Delivery');
    expect(Visit::withoutGlobalScopes()->count())->toBe(0);
});

it('is idempotent: a re-posted final step never double-logs or double-notifies', function () {
    $payload = ['sessionId' => 'DUP1', 'text' => '1001*2', 'phoneNumber' => '+256700111222'];

    postUssd($payload)->assertOk();
    postUssd($payload)->assertOk(); // AT retries the same session/step

    expect(Visit::withoutGlobalScopes()->where('ussd_session_id', 'DUP1')->count())->toBe(1);
    Queue::assertPushed(NotifyVisitCheckedIn::class, 1);
});

it('always replies 200 with a text/plain body', function () {
    $res = postUssd(['text' => '']);

    $res->assertOk();
    $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

it('matches tenant codes case-insensitively and trims whitespace', function () {
    Tenant::factory()->withCode('VIP')->create(['building_id' => $this->building->id, 'name' => 'VIP Suite']);

    expect(postUssd(['text' => ' vip '])->getContent())
        ->toContain('You are visiting VIP Suite');
});
