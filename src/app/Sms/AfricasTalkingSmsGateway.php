<?php

namespace App\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real Africa's Talking SMS gateway.
 *
 * VERIFY against the official AT docs before live use (Steven, action #1). The
 * endpoint/fields below follow the standard AT bulk-SMS convention; if the
 * confirmed API differs, this class is the only thing that changes.
 *
 * Selected by setting AT_DRIVER=africastalking once sandbox credentials exist.
 * Until then the default LogSmsGateway is used and nothing leaves the box.
 */
class AfricasTalkingSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $username,
        private readonly string $apiKey,
        private readonly ?string $senderId = null,
        private readonly string $baseUrl = 'https://api.africastalking.com/version1',
    ) {}

    public function send(array $recipients, string $message): int
    {
        $recipients = array_values(array_filter($recipients));

        if ($recipients === []) {
            return 0;
        }

        $payload = [
            'username' => $this->username,
            'to' => implode(',', $recipients), // AT accepts comma-separated MSISDNs.
            'message' => $message,
        ];

        if ($this->senderId) {
            $payload['from'] = $this->senderId;
        }

        $response = Http::asForm()
            ->withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->post("{$this->baseUrl}/messaging", $payload);

        if ($response->failed()) {
            // Throw so the queue retries (backoff configured on the worker).
            Log::warning('[SMS:AT] send failed', ['status' => $response->status()]);
            $response->throw();
        }

        return count($recipients);
    }
}
