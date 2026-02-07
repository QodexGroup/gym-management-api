<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TestQueueJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $testMessage = 'Queue test'
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $timestamp = Carbon::now()->toDateTimeString();
        $message = "✅ QUEUE WORKER IS WORKING! Test executed at: {$timestamp}. Message: {$this->testMessage}";

        Log::info('TestQueueJob executed successfully', [
            'timestamp' => $timestamp,
            'message' => $this->testMessage,
            'job_id' => $this->job->getJobId() ?? 'unknown',
            'queue' => $this->job->getQueue() ?? 'default',
        ]);

        // Also output to stderr/stdout for Cloud Run logs
        error_log($message);

        // You can also check the logs table or failed_jobs table to verify
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('TestQueueJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
