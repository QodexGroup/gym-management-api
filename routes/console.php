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

// Account subscription billing: generate invoices on 5th, lock accounts on 10th
Schedule::command('account-billing:generate-invoices')->monthlyOn(5, '06:00');
Schedule::command('account-billing:lock-accounts')->monthlyOn(10, '06:00');

// Deactivate delinquent accounts on last day of month at 00:00 (Asia/Manila)
Schedule::command('account-billing:deactivate-accounts')->lastDayOfMonth('00:00');

// Referrals safety net: qualify pending referrals whose invitee has since paid - weekly Mon 02:00 (Asia/Manila)
Schedule::command('referrals:evaluate-pending')->weeklyOn(1, '02:00');
