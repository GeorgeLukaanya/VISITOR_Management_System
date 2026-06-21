<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Sms\AfricasTalkingSmsGateway;
use App\Sms\LogSmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the SMS gateway from the configured driver. Defaults to the
        // log gateway so dev/sandbox never hit the network or need credentials.
        $this->app->singleton(SmsGateway::class, function ($app) {
            $config = $app['config']['services.africastalking'];

            return match ($config['driver'] ?? 'log') {
                'africastalking' => new AfricasTalkingSmsGateway(
                    username: (string) $config['username'],
                    apiKey: (string) $config['api_key'],
                    senderId: $config['sender_id'] ?: null,
                ),
                default => new LogSmsGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
