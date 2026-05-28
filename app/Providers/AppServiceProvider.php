<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Providers\RouteParameterCastingServiceProvider;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(RouteParameterCastingServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Brevo mail transport
        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory())->create(
                new Dsn('brevo+api', 'default', config('services.brevo.key'))
            );
        });

        // Configure rate limiter for email sending jobs
        // Mailtrap free tier limit: 1 email per 10 seconds (rolling window)
        // Using perMinute(6) = 6 emails per minute ≈ 1 email per 10 seconds
        RateLimiter::for('emails', function ($job) {
            return Limit::perMinute(6)->by('email-sending');
        });
    }
}
