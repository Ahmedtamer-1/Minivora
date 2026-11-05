<?php

// THE SCHEDULE: every timed job in one place (run by one cron line, every minute).
// Later: hourly proactive checks, daily checks, 09:00 Africa/Cairo morning brief, nightly imports
// and backups, weekly briefings.

use Illuminate\Support\Facades\Schedule;

// Shared hosting has no always-running worker: drain the database queue in short batches.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// Tamper check on the activity log. NFR-SEC-10
Schedule::command('activity-log:verify')->dailyAt('03:00')->timezone('Africa/Cairo');
