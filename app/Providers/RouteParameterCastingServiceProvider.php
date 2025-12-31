<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteParameterCastingServiceProvider extends ServiceProvider
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
        // Register standard 'id' parameter immediately
        // This ensures at least 'id' works even if scanning fails
        Route::pattern('id', '[0-9]+');
        Route::bind('id', function ($value) {
            if (is_numeric($value) && $value !== '' && $value !== null) {
                return (int)$value;
            }
            return $value;
        });

        // Register route bindings for parameters containing "id"
        // Try to register immediately - routes should be loaded by boot() time
        $this->registerRouteParameterCasting();

        // Also register in booted() as a safety net in case routes load late
        // The static variable will prevent duplicate registrations
        $this->app->booted(function () {
            $this->registerRouteParameterCasting();
        });
    }

    /**
     * Register route parameter casting for parameters containing "id"
     * This scans all routes and registers specific bindings for better performance.
     */
    protected function registerRouteParameterCasting(): void
    {
        // Use static variables to track registered parameters and scan status
        static $registeredParams = ['id']; // Start with 'id' which we already registered
        static $hasScanned = false; // Track if we've already scanned routes

        // If we've already scanned and registered all parameters, skip
        if ($hasScanned) {
            return;
        }

        // Get all registered routes
        $routes = Route::getRoutes();

        // Scan all routes to find parameter names dynamically
        foreach ($routes as $route) {
            $parameters = $route->parameterNames();

            foreach ($parameters as $paramName) {
                // Skip if already registered
                if (in_array($paramName, $registeredParams)) {
                    continue;
                }

                // Check if parameter name contains "id" (case-insensitive)
                // But exclude parameters like "scanType" which should remain strings
                if (stripos($paramName, 'id') !== false && stripos($paramName, 'type') === false) {
                    // Set route pattern to validate parameter is numeric
                    Route::pattern($paramName, '[0-9]+');

                    // Cast to integer during route resolution
                    // Only cast if the value is numeric
                    Route::bind($paramName, function ($value) {
                        // Cast to integer if numeric
                        if (is_numeric($value) && $value !== '' && $value !== null) {
                            return (int)$value;
                        }
                        // If invalid, return as-is to let Laravel handle the error
                        return $value;
                    });

                    $registeredParams[] = $paramName;
                }
            }
        }

        // Mark as scanned so we don't scan again
        $hasScanned = true;
    }
}

