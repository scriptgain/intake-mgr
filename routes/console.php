<?php

use Illuminate\Support\Facades\Schedule;

// Nightly housekeeping: expire abandoned carts, prune audit rows.
Schedule::command('shop:housekeeping')->dailyAt('03:30')->withoutOverlapping();

// Self-update: check every few minutes and auto-apply a newer signed release
// soon after it's published, unless the operator has turned auto-update off.
Schedule::command('app:update')
    ->everyFiveMinutes()
    ->when(fn () => \App\Services\UpdateService::autoEnabled())
    ->withoutOverlapping();

// Admin "Update Now" requests: applied within a minute by the scheduler so the
// command runs with the right PHP binary/user rather than shelling out from fpm.
Schedule::command('app:update')
    ->everyMinute()
    ->when(fn () => \App\Models\Setting::get('update_requested') === '1')
    ->withoutOverlapping();

// Automated database backup at the configured time; the command self-gates on
// the enabled flag + daily/weekly frequency.
Schedule::command('db-backup:run')
    ->dailyAt(rescue(fn () => \App\Models\Setting::get('dbbackup_time'), null, false) ?: '02:30')
    ->withoutOverlapping();

Schedule::command('db-backup:run --force')
    ->everyMinute()
    ->when(fn () => \App\Models\Setting::get('dbbackup_requested') === '1')
    ->withoutOverlapping();
