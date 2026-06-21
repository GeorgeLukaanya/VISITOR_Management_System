<?php

namespace App\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Default SMS gateway for local/dev/sandbox: writes the message to the log
 * instead of hitting the network. No credentials required, no cost, and the
 * sandbox never sends real SMS (CLAUDE.md privacy/sandbox constraints).
 */
class LogSmsGateway implements SmsGateway
{
    public function send(array $recipients, string $message): int
    {
        $recipients = array_values(array_filter($recipients));

        if ($recipients === []) {
            return 0;
        }

        Log::info('[SMS:log] would send SMS', [
            'to' => $recipients,
            'message' => $message,
        ]);

        return count($recipients);
    }
}
