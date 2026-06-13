<?php

namespace App\Logging;

use Illuminate\Support\Facades\Http;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

/**
 * Ships log records to the New Relic Log API so logs survive Railway redeploys.
 *
 * New Relic Logs Ingest API: https://docs.newrelic.com/docs/logs/log-api/introduction-log-api/
 */
class NewRelicLogHandler extends AbstractProcessingHandler
{
    private const ENDPOINT = 'https://log-api.newrelic.com/log/v1';

    public function __construct(
        private readonly string $licenseKey,
        private readonly string $appName,
        int|string $level = 'debug',
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (empty($this->licenseKey)) {
            return;
        }

        $payload = [
            'timestamp' => (int) floor(((float) $record->datetime->format('U.u')) * 1000),
            'message' => $record->message,
            'attributes' => array_merge(
                [
                    'service.name' => $this->appName,
                    'logger' => $record->channel,
                    'level' => $record->level->getName(),
                    'context' => $record->context,
                    'extra' => $record->extra,
                ],
                $this->newRelicLinkingMetadata(),
            ),
        ];

        try {
            // Fire-and-forget with a short timeout so a New Relic outage never
            // blocks the request lifecycle.
            Http::withHeaders(['Api-Key' => $this->licenseKey])
                ->timeout(2)
                ->connectTimeout(1)
                ->post(self::ENDPOINT, $payload);
        } catch (Throwable) {
            // Never let log shipping failures break the application.
        }
    }

    /**
     * Attach New Relic distributed tracing metadata (trace.id / span.id) when
     * the PHP agent is active, so logs link to APM traces automatically.
     */
    private function newRelicLinkingMetadata(): array
    {
        if (! extension_loaded('newrelic') || ! function_exists('newrelic_get_linking_metadata')) {
            return [];
        }

        return newrelic_get_linking_metadata();
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonFormatter();
    }
}
