<?php

namespace App\Services\Ussd;

use App\Enums\VisitPurpose;
use App\Models\Tenant;

/**
 * The two-screen visitor check-in state machine (CLAUDE.md "USSD flow").
 *
 * It is pure: given a request it returns a UssdResponse and performs NO I/O —
 * no DB writes, no SMS, no broadcasts. The controller is responsible for acting
 * on a returned CheckInIntent (persist + dispatch) and keeping the request fast.
 *
 * Progress is derived entirely from the accumulated `text` (steps), because USSD
 * sessions are stateless across POSTs.
 *
 *   steps = []            -> Screen 1: ask for the tenant routing code
 *   steps = [code]        -> resolve code -> Screen 2 (purpose menu) | END invalid
 *   steps = [code, pick*] -> validate purpose -> END (check-in) | re-show menu
 */
class UssdSessionService
{
    public function handle(UssdRequest $request): UssdResponse
    {
        $steps = $request->steps();

        // Screen 1 — session start.
        if (count($steps) === 0) {
            return UssdResponse::con('Enter the code of who you are visiting:');
        }

        // The routing code is always the first thing the visitor entered.
        $tenant = Tenant::resolveByCode($steps[0]);

        if (! $tenant) {
            return UssdResponse::end('Code not recognised. Please check the posted list.');
        }

        // Screen 2 — code resolved, but no purpose chosen yet.
        if (count($steps) === 1) {
            return $this->purposeMenu($tenant);
        }

        // Purpose step. Use the latest entry so an out-of-range retry re-prompts
        // without adding a third screen.
        $purpose = VisitPurpose::fromInput(end($steps));

        if (! $purpose) {
            return $this->purposeMenu($tenant, invalid: true);
        }

        // Terminal step — hand the controller everything it needs to log the visit.
        return UssdResponse::end(
            'Thank you. Your arrival has been logged.',
            new CheckInIntent(
                tenant: $tenant,
                purpose: $purpose,
                visitorPhone: $request->phoneNumber,
                sessionId: $request->sessionId,
            ),
        );
    }

    private function purposeMenu(Tenant $tenant, bool $invalid = false): UssdResponse
    {
        $prefix = $invalid ? 'Invalid choice. ' : '';

        return UssdResponse::con(
            $prefix."You are visiting {$tenant->name}. Reason: ".VisitPurpose::menu()
        );
    }
}
