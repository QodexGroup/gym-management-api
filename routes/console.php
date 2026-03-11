<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule membership status update - Run daily at midnight (Asia/Manila)
Schedule::command('membership:update-expired-status')
    ->dailyAt('00:00');

// Schedule membership expiration notifications - Run daily at 9:00 AM (Asia/Manila)
Schedule::command('membership:check-expiration')
    ->dailyAt('09:00');

// Prune expired database cache entries - Run daily at 1:00 AM (Asia/Manila)
Schedule::command('cache:prune-stale --driver=database')
    ->dailyAt('01:00');

// Account subscription billing: generate invoices on 5th, lock accounts on 10th
Schedule::command('account-billing:generate-invoices')->monthlyOn(5, '06:00');
Schedule::command('account-billing:lock-accounts')->monthlyOn(10, '06:00');
