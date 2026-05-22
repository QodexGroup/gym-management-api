<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TestQueueJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 30;

    public function __construct(
        public string $testMessage = 'Queue test'
    ) {
    }

    public function handle(): void
    {
        Log::info('TestQueueJob processed', [
            'timestamp' => now()->toIso8601String(),
            'message' => $this->testMessage,
            'job_id' => $this->job ? $this->job->getJobId() : null,
            'queue' => $this->job ? $this->job->getQueue() : null,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TestQueueJob failed', [
            'error' => $exception->getMessage(),
            'message' => $this->testMessage,
        ]);
    }
}
