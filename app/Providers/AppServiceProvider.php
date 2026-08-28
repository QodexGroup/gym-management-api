<?php

namespace App\Providers;

use App\Constant\PublicRegistrationConstant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        // Throttle Google Sheets webhook syncs (Apps Script quotas).
        // 30/min = one append every ~2s; rate-limited jobs are released
        // back onto the queue, not lost.
        RateLimiter::for('google-sheets', function ($job) {
            return Limit::perMinute(30)->by('google-sheets-sync');
        });

        // Public member self-registration. Customer creation is unmetered in
        // this system (max_customers is never read anywhere), so these two
        // limits are the only thing standing between a public link and an
        // unbounded flood of junk member records.
        //
        // The per-IP limit mostly slows scripted probing of the duplicate-phone
        // response. The per-gym daily ceiling is the real backstop: per-IP
        // limits are weak here, where CGNAT puts many genuine users behind one
        // address and a bot rotates addresses at will.
        RateLimiter::for(PublicRegistrationConstant::RATE_LIMITER, function (Request $request) {
            return [
                Limit::perMinute(PublicRegistrationConstant::MAX_PER_MINUTE_PER_IP)->by($request->ip()),
                Limit::perDay(PublicRegistrationConstant::MAX_PER_DAY_PER_GYM)
                    ->by('gym:' . $request->route('publicCode')),
            ];
        });
    }
}
