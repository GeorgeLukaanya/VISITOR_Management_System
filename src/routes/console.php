<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enforce the DPA retention window daily: delete visits older than
// VISIT_RETENTION_DAYS (config visits.retention_days). A window of 0 disables
// it. Requires the scheduler to be running (`php artisan schedule:work`, or a
// cron entry calling `schedule:run` every minute) in deployed environments.
Schedule::command('visits:prune')->dailyAt('02:30');
