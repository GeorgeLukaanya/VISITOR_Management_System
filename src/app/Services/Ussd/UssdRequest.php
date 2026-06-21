<?php

namespace App\Services\Ussd;

/**
 * Normalised view of one Africa's Talking USSD callback POST.
 *
 * AT sends the same session id on every step, plus `text` — the accumulated
 * user input with each step joined by `*` (see CLAUDE.md). Sessions are
 * stateless across POSTs: everything we know about progress is in `text`.
 *
 * This DTO is aggregator-agnostic on purpose — if the confirmed AT field names
 * differ, only the controller's mapping changes, not the state machine.
 */
final class UssdRequest
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $phoneNumber,
        public readonly string $serviceCode,
        public readonly string $text,
    ) {}

    /**
     * The individual steps the visitor has entered so far.
     *
     * @return list<string>
     */
    public function steps(): array
    {
        if ($this->text === '') {
            return [];
        }

        return array_map('trim', explode('*', $this->text));
    }
}
