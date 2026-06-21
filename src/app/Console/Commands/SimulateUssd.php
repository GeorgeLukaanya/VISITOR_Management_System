<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Drive the USSD flow locally without a telco by POSTing Africa's-Talking-shaped
 * payloads at our own callback (CLAUDE.md "Local development & testing").
 *
 * Interactive (default) — emulates a handset, prompting for each screen:
 *     php artisan ussd:simulate
 *
 * Scripted — replay an accumulated `text` in one shot (handy in scripts/CI):
 *     php artisan ussd:simulate --text="1001*1"
 *
 * Point it at a different endpoint (e.g. the AT sandbox simulator, once Steven
 * provides access) with --url:
 *     php artisan ussd:simulate --url="https://<ngrok-host>/ussd"
 *
 * Default URL is the nginx service so it works from inside the app container
 * (`make ussd`). From the host use --url="http://localhost:8088/ussd".
 */
class SimulateUssd extends Command
{
    protected $signature = 'ussd:simulate
        {--phone=+256700000001 : Visitor MSISDN (AT captures this from the SIM)}
        {--url= : Callback URL to POST to (default: env USSD_SIMULATOR_URL or http://nginx/ussd)}
        {--service-code= : The dialled USSD service code (default *384*1234#)}
        {--session= : Reuse a specific session id (default: random)}
        {--text= : Non-interactive: post this accumulated text once and exit}';

    protected $description = 'Simulate an Africa\'s Talking USSD session against the local callback';

    public function handle(): int
    {
        $url = $this->option('url')
            ?: env('USSD_SIMULATOR_URL', 'http://nginx/ussd');
        $phone = (string) $this->option('phone');
        $serviceCode = (string) ($this->option('service-code') ?: '*384*1234#');
        $sessionId = (string) ($this->option('session') ?: 'sim_'.Str::random(16));

        $this->line("<comment>Session:</comment> {$sessionId}");
        $this->line("<comment>Phone:</comment>   {$phone}");
        $this->line("<comment>Endpoint:</comment> {$url}");
        $this->newLine();

        // Non-interactive single shot.
        if ($this->option('text') !== null) {
            $this->post($url, $sessionId, $phone, $serviceCode, (string) $this->option('text'));

            return self::SUCCESS;
        }

        // Interactive: accumulate input across screens, joined by '*', like AT does.
        $text = '';

        while (true) {
            $body = $this->post($url, $sessionId, $phone, $serviceCode, $text);

            if ($body === null) {
                return self::FAILURE;
            }

            if (Str::startsWith($body, 'END')) {
                break; // Session closed.
            }

            $input = $this->ask('Your input');
            $text = $text === '' ? (string) $input : $text.'*'.$input;
        }

        $this->newLine();
        $this->info('Session ended.');

        return self::SUCCESS;
    }

    /** POST one AT-shaped step and render the reply. Returns the raw body. */
    private function post(string $url, string $sessionId, string $phone, string $serviceCode, string $text): ?string
    {
        try {
            $response = Http::asForm()->post($url, [
                'sessionId' => $sessionId,
                'phoneNumber' => $phone,
                'serviceCode' => $serviceCode,
                'networkCode' => '62001',
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            $this->error("Request failed: {$e->getMessage()}");

            return null;
        }

        $body = $response->body();

        $this->newLine();
        $this->line('<fg=cyan>──── screen ────</>');
        $this->line($body);
        $this->line('<fg=cyan>────────────────</>');

        return $body;
    }
}
