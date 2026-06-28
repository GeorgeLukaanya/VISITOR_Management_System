<?php

use App\Models\Building;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

/**
 * Covers VerifyUssdCallback: the USSD callback is open while AT_CALLBACK_SECRET
 * is empty (default), and authenticated by header or query param once it's set.
 */
beforeEach(function () {
    Queue::fake();
    $building = Building::factory()->create();
    Tenant::factory()->withCode('1001')->create([
        'building_id' => $building->id,
        'name' => 'Acme Bank',
    ]);
});

it('leaves the callback open when no secret is configured (default)', function () {
    config()->set('services.africastalking.callback_secret', '');

    $this->post('/ussd', ussdPayload(['text' => '1001']))->assertOk();
});

it('accepts the callback when the secret matches via header', function () {
    config()->set('services.africastalking.callback_secret', 's3cret');

    $this->withHeader('X-Callback-Secret', 's3cret')
        ->post('/ussd', ussdPayload(['text' => '1001']))
        ->assertOk();
});

it('accepts the callback when the secret matches via query string', function () {
    config()->set('services.africastalking.callback_secret', 's3cret');

    $this->post('/ussd?secret=s3cret', ussdPayload(['text' => '1001']))->assertOk();
});

it('rejects the callback with 403 when the secret is missing', function () {
    config()->set('services.africastalking.callback_secret', 's3cret');

    $this->post('/ussd', ussdPayload(['text' => '1001']))->assertForbidden();
});

it('rejects the callback with 403 when the secret is wrong', function () {
    config()->set('services.africastalking.callback_secret', 's3cret');

    $this->withHeader('X-Callback-Secret', 'nope')
        ->post('/ussd', ussdPayload(['text' => '1001']))
        ->assertForbidden();
});
