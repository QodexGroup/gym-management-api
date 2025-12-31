<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule membership status update - Run daily at midnight
Schedule::command('membership:update-expired-status')
    ->dailyAt('00:00')
    ->timezone('Asia/Manila');
// Schedule membership expiration notifications - Run daily at 9:00 AM
Schedule::command('membership:check-expiration')
    ->dailyAt('09:00')
    ->timezone('Asia/Manila');

