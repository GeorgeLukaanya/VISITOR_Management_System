<?php

namespace App\Console\Commands;

use App\Models\Visit;
use Illuminate\Console\Command;

/**
 * Delete visit records past the configured retention window.
 *
 * The whole point vs. the paper book is that visitor PII does not live forever
 * (README "Privacy & data protection" / Uganda DPA). The window is
 * config('visits.retention_days') (env VISIT_RETENTION_DAYS). This command is
 * scheduled daily in routes/console.php and is safe to run by hand any time.
 *
 *     php artisan visits:prune              # use the configured window
 *     php artisan visits:prune --days=90    # override the window for this run
 *     php artisan visits:prune --dry-run    # report what would go, delete nothing
 *
 * A window of 0 (or negative) means "retain indefinitely" — the command then
 * does nothing, so disabling retention is just VISIT_RETENTION_DAYS=0.
 */
class PruneVisits extends Command
{
    protected $signature = 'visits:prune
        {--days= : Override the retention window (days) for this run}
        {--dry-run : Report how many visits would be pruned without deleting}';

    protected $description = 'Delete visit records older than the DPA retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('visits.retention_days'));

        if ($days <= 0) {
            $this->info('Retention is disabled (window <= 0 days); nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->startOfDay();

        // No authenticated user in the console context, so the tenant global
        // scope is already a no-op here; withoutGlobalScopes() makes that
        // explicit and future-proof — a prune must span every tenant.
        $stale = fn () => Visit::withoutGlobalScopes()->where('checked_in_at', '<', $cutoff);

        $count = $stale()->count();

        if ($count === 0) {
            $this->info("No visits older than {$days} days (before {$cutoff->toDateString()}).");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[dry-run] Would prune {$count} visit(s) older than {$days} days (before {$cutoff->toDateString()}).");

            return self::SUCCESS;
        }

        // Delete in batches so the operation stays memory-flat and avoids one
        // huge lock on large tables. Selecting ids then deleting by key works
        // across Postgres/SQLite alike (neither supports DELETE ... LIMIT).
        $deleted = 0;
        do {
            $ids = $stale()->limit(1000)->pluck('id');
            $deleted += Visit::withoutGlobalScopes()->whereKey($ids)->delete();
        } while ($ids->isNotEmpty());

        $this->info("Pruned {$deleted} visit(s) older than {$days} days (before {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
