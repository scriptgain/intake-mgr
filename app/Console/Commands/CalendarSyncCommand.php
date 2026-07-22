<?php

namespace App\Console\Commands;

use App\Services\Calendar\CalendarSync;
use Illuminate\Console\Command;

/**
 * Pulls busy times from every connected calendar so availability stays current.
 * Meant for cron (e.g. every 15 minutes); pushing work orders happens
 * synchronously as they change, so this is pull-only.
 */
class CalendarSyncCommand extends Command
{
    protected $signature = 'calendar:sync';

    protected $description = 'Refresh cached busy times from all connected calendars.';

    public function handle(CalendarSync $sync): int
    {
        $count = $sync->syncAll();
        $this->info("Synced {$count} calendar connection(s).");

        return self::SUCCESS;
    }
}
