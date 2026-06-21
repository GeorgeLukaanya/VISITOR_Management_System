<?php

namespace Tests\Fakes;

use App\Contracts\SmsGateway;

/**
 * In-memory SMS gateway for tests: records every send() instead of hitting the
 * network, so tests can assert exactly how many SMS attempts were made and to whom.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{recipients: list<string>, message: string}> */
    public array $sent = [];

    public function send(array $recipients, string $message): int
    {
        $recipients = array_values(array_filter($recipients));

        $this->sent[] = ['recipients' => $recipients, 'message' => $message];

        return count($recipients);
    }

    public function sendCount(): int
    {
        return count($this->sent);
    }

    /** @return list<string> */
    public function allRecipients(): array
    {
        return array_merge(...array_column($this->sent, 'recipients')) ?: [];
    }
}
