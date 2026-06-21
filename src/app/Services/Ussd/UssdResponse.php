<?php

namespace App\Services\Ussd;

/**
 * The reply the state machine wants to send back to Africa's Talking.
 *
 *  - CON  -> keep the session open, show the next screen.
 *  - END  -> close the session with a final message.
 *
 * `intent` is set only on a successful terminal step; the controller acts on it
 * (persist + dispatch) before returning body(). The service performs no I/O.
 */
final class UssdResponse
{
    private function __construct(
        public readonly bool $continues,
        public readonly string $message,
        public readonly ?CheckInIntent $intent = null,
    ) {}

    /** Keep the session open (CON). */
    public static function con(string $message): self
    {
        return new self(true, $message);
    }

    /** Close the session (END). */
    public static function end(string $message, ?CheckInIntent $intent = null): self
    {
        return new self(false, $message, $intent);
    }

    /** The wire body AT expects: "CON ..." or "END ...". */
    public function body(): string
    {
        return ($this->continues ? 'CON ' : 'END ').$this->message;
    }
}
