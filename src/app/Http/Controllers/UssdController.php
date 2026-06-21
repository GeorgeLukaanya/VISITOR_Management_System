<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Jobs\NotifyVisitCheckedIn;
use App\Models\Visit;
use App\Services\Ussd\CheckInIntent;
use App\Services\Ussd\UssdRequest;
use App\Services\Ussd\UssdSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Africa's Talking USSD callback endpoint.
 *
 * Hard rules (CLAUDE.md): keep this FAST. It only — (1) maps the AT payload,
 * (2) asks the state machine what to say, (3) on a completed flow persists the
 * visit and dispatches the off-session notification job, (4) returns a plain
 * "CON"/"END" body with HTTP 200. No SMS, no websocket, no third-party HTTP here.
 */
class UssdController extends Controller
{
    public function __construct(private readonly UssdSessionService $sessions) {}

    public function __invoke(Request $request): Response
    {
        // AT field names per CLAUDE.md; tolerate absence so a malformed callback
        // still gets a well-formed USSD reply rather than a 500.
        $ussd = new UssdRequest(
            sessionId: (string) $request->input('sessionId', ''),
            phoneNumber: (string) $request->input('phoneNumber', ''),
            serviceCode: (string) $request->input('serviceCode', ''),
            text: (string) $request->input('text', ''),
        );

        $response = $this->sessions->handle($ussd);

        if ($response->intent) {
            $this->recordCheckIn($response->intent);
        }

        // USSD replies are plain text; always 200 (CLAUDE.md "Hard rules").
        return response($response->body(), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Persist the visit and queue the notification.
     *
     * Idempotent on ussd_session_id (unique column): if AT re-posts the final
     * step we reuse the existing visit and never create a duplicate. The job is
     * only dispatched when the visit is first created.
     */
    private function recordCheckIn(CheckInIntent $intent): void
    {
        $visit = Visit::firstOrCreate(
            ['ussd_session_id' => $intent->sessionId],
            [
                'building_id' => $intent->tenant->building_id,
                'tenant_id' => $intent->tenant->id,
                'visitor_phone' => $intent->visitorPhone,
                'purpose' => $intent->purpose->label(),
                'status' => VisitStatus::CheckedIn,
                'checked_in_at' => now(),
            ],
        );

        if ($visit->wasRecentlyCreated) {
            NotifyVisitCheckedIn::dispatch($visit->id);
        }
    }
}
