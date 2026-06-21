<?php

namespace App\Contracts;

/**
 * Abstraction over the SMS provider so the rest of the app never depends on
 * Africa's Talking directly (CLAUDE.md: "keep the aggregator integration behind
 * an interface so swapping in confirmed details is a small change").
 *
 * One call = one send attempt to one or more recipients, mirroring AT's bulk
 * SMS endpoint (comma-separated recipients in a single request).
 */
interface SmsGateway
{
    /**
     * @param  list<string>  $recipients  E.164 MSISDNs.
     * @return int  Number of recipients the message was accepted for.
     */
    public function send(array $recipients, string $message): int;
}
