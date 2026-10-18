<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Automation. In production one cron entry runs everything:
 *   * * * * * cd /path/to/letting-world-api && php artisan schedule:run >> /dev/null 2>&1
 * Locally: php artisan schedule:work
 */

// Certificate expiry (60/30/14/7 days, expired + weekly) and tenancy end alerts.
Schedule::command('alerts:send-expiry')->dailyAt('08:00')->timezone('Europe/London')->withoutOverlapping();

// 11Done connection safety net: resend failed bookings, refresh statuses, fetch certificates.
Schedule::command('elevendone:sync')->everyFiveMinutes()->withoutOverlapping();
