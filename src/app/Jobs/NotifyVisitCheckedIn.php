<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Events\VisitCheckedIn;
use App\Models\Visit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Off-session notification for a check-in (CLAUDE.md "Off-session notification job").
 *
 * Runs on the queue AFTER the USSD session ends so the USSD request stays fast.
 * On a check-in it:
 *   1. sends ONE SMS (to the guard(s) and/or tenant contact, per the tenant's
 *      notify_* settings), and
 *   2. broadcasts a Reverb event to the guard tablet.
 *
 * Idempotency: it atomically "claims" the visit by setting notified_at only when
 * it is still null. A retried or duplicate run finds nothing to claim and exits
 * without re-sending. If the send itself fails the claim is released so the
 * queue's retry can try again.
 */
class NotifyVisitCheckedIn implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $visitId) {}

    public function handle(SmsGateway $sms): void
    {
        // Atomic claim — exactly one run proceeds past this point per visit.
        $claimed = Visit::withoutGlobalScopes()
            ->whereKey($this->visitId)
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($claimed === 0) {
            return; // Already notified (or visit gone) — idempotent no-op.
        }

        $visit = Visit::withoutGlobalScopes()
            ->with(['tenant', 'building'])
            ->find($this->visitId);

        if (! $visit) {
            return;
        }

        try {
            $sms->send($this->recipients($visit), $this->message($visit));
            VisitCheckedIn::dispatch($visit);
        } catch (\Throwable $e) {
            // Release the claim so a retry can re-attempt the notification.
            Visit::withoutGlobalScopes()->whereKey($visit->id)->update(['notified_at' => null]);
            throw $e;
        }
    }

    /**
     * SMS recipients, deduped: building guards (when the tenant wants guard
     * alerts) plus the tenant's own contact (when it wants tenant alerts).
     *
     * @return list<string>
     */
    private function recipients(Visit $visit): array
    {
        $tenant = $visit->tenant;
        $recipients = [];

        if ($tenant?->notify_guard) {
            $recipients = $visit->building
                ?->guards()
                ->pluck('phone')
                ->all() ?? [];
        }

        if ($tenant?->notify_tenant && $tenant->contact_phone) {
            $recipients[] = $tenant->contact_phone;
        }

        return array_values(array_unique(array_filter($recipients)));
    }

    private function message(Visit $visit): string
    {
        $time = $visit->checked_in_at?->format('H:i') ?? now()->format('H:i');

        // Phase 1: phone + destination + time only. No registered name (Phase 2).
        return sprintf(
            'Visitor %s arrived for %s at %s (%s).',
            $visit->visitor_phone,
            $visit->tenant?->name ?? 'your office',
            $time,
            $visit->purpose,
        );
    }
}
