<?php

namespace App\Logging;

use Monolog\Logger;

/**
 * Factory for the "newrelic" custom Monolog channel.
 *
 * Registered in config/logging.php as:
 *   'newrelic' => [
 *       'driver' => 'custom',
 *       'via' => \App\Logging\NewRelicLoggerFactory::class,
 *       'level' => env('LOG_LEVEL', 'debug'),
 *   ]
 */
class NewRelicLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('newrelic');

        $logger->pushHandler(new NewRelicLogHandler(
            licenseKey: (string) env('NEW_RELIC_LICENSE_KEY', ''),
            appName: (string) env('NEW_RELIC_APP_NAME', config('app.name')),
            level: $config['level'] ?? 'debug',
        ));

        return $logger;
    }
}
