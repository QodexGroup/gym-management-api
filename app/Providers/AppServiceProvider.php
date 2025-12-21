<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure rate limiter for email sending jobs
        // Mailtrap free tier limit: 1 email per 10 seconds (rolling window)
        RateLimiter::for('emails', function ($job) {
            return Limit::perSeconds(10, 1)->by('email-sending');
        });
    }
}
