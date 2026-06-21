<?php

namespace App\Services\Ussd;

use App\Enums\VisitPurpose;
use App\Models\Tenant;

/**
 * The decision the state machine reached on a completed flow: "log this check-in".
 *
 * The service produces this; the controller turns it into a persisted Visit plus
 * a dispatched notification job. Keeping it as data (not a side effect) is what
 * makes the state machine pure and unit-testable.
 */
final class CheckInIntent
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly VisitPurpose $purpose,
        public readonly string $visitorPhone,
        public readonly string $sessionId,
    ) {}
}
