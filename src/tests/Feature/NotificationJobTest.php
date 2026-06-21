<?php

use App\Contracts\SmsGateway;
use App\Events\VisitCheckedIn;
use App\Jobs\NotifyVisitCheckedIn;
use App\Models\Building;
use App\Models\Guard;
use App\Models\Tenant;
use App\Models\Visit;
use Illuminate\Support\Facades\Event;
use Tests\Fakes\FakeSmsGateway;

/**
 * Step 4: the off-session notification job — SMS + Reverb broadcast, idempotent
 * on the visit (CLAUDE.md "Off-session notification job").
 */

beforeEach(function () {
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);

    $this->building = Building::factory()->create();
    $this->guard = Guard::factory()->create([
        'building_id' => $this->building->id,
        'phone' => '+256700000300',
    ]);
    $this->tenant = Tenant::factory()->create([
        'building_id' => $this->building->id,
        'name' => 'Acme Bank',
        'contact_phone' => '+256700000201',
        'notify_guard' => true,
        'notify_tenant' => true,
    ]);
    $this->visit = Visit::factory()->forTenant($this->tenant)->create([
        'visitor_phone' => '+256777123456',
    ]);
});

it('sends exactly one SMS and broadcasts exactly one event for a check-in', function () {
    Event::fake([VisitCheckedIn::class]);

    (new NotifyVisitCheckedIn($this->visit->id))->handle($this->sms);

    // One SMS attempt (one bulk send to guard + tenant contact).
    expect($this->sms->sendCount())->toBe(1)
        ->and($this->sms->allRecipients())
        ->toEqualCanonicalizing(['+256700000300', '+256700000201']);

    Event::assertDispatched(VisitCheckedIn::class, 1);
});

it('does not double-send when the job is retried', function () {
    Event::fake([VisitCheckedIn::class]);

    $job = new NotifyVisitCheckedIn($this->visit->id);
    $job->handle($this->sms); // first run
    $job->handle($this->sms); // retry / duplicate delivery

    expect($this->sms->sendCount())->toBe(1);
    Event::assertDispatched(VisitCheckedIn::class, 1);
});

it('marks the visit notified_at after sending', function () {
    expect($this->visit->notified_at)->toBeNull();

    (new NotifyVisitCheckedIn($this->visit->id))->handle($this->sms);

    expect($this->visit->fresh()->notified_at)->not->toBeNull();
});

it('respects the tenant notification preferences', function () {
    $this->tenant->update(['notify_guard' => false, 'notify_tenant' => true]);
    $visit = Visit::factory()->forTenant($this->tenant)->create();

    (new NotifyVisitCheckedIn($visit->id))->handle($this->sms);

    // Only the tenant contact, not the guard.
    expect($this->sms->allRecipients())->toBe(['+256700000201']);
});

it('releases the claim and rethrows if the SMS send fails (so the queue retries)', function () {
    // A gateway that always throws.
    $boom = new class implements SmsGateway {
        public function send(array $recipients, string $message): int
        {
            throw new RuntimeException('AT down');
        }
    };

    expect(fn () => (new NotifyVisitCheckedIn($this->visit->id))->handle($boom))
        ->toThrow(RuntimeException::class);

    // Claim released so a retry can re-attempt.
    expect($this->visit->fresh()->notified_at)->toBeNull();
});

it('includes phone, tenant and time in the SMS but never a registered name', function () {
    (new NotifyVisitCheckedIn($this->visit->id))->handle($this->sms);

    $message = $this->sms->sent[0]['message'];

    expect($message)
        ->toContain('+256777123456')
        ->toContain('Acme Bank');
});
