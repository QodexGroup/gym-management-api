<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule membership status update - Run daily at midnight
Schedule::command('membership:update-expired-status')
    ->dailyAt('00:00')
    ->timezone('Asia/Manila');