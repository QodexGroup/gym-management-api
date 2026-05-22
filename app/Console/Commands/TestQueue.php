<?php

namespace App\Console\Commands;

use App\Jobs\TestQueueJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestQueue extends Command
{
    protected $signature = 'queue:test {message?} {--sync : Process the test job immediately without queueing}';

    protected $description = 'Dispatch a test queue job to verify the worker can process jobs';

    public function handle(): int
    {
        $message = $this->argument('message')
            ?? 'Test queue job dispatched at ' . now()->toDateTimeString();

        if ($this->option('sync')) {
            (new TestQueueJob($message))->handle();
            $this->info('Test job processed synchronously.');
        } else {
            TestQueueJob::dispatch($message);
            $this->info('Test job dispatched to the queue.');
        }

        $this->newLine();
        $this->info('Queue status:');
        $this->line('  Connection: ' . config('queue.default'));
        $this->line('  Pending jobs: ' . DB::table('jobs')->count());
        $this->line('  Failed jobs: ' . DB::table('failed_jobs')->count());

        return Command::SUCCESS;
    }
}
