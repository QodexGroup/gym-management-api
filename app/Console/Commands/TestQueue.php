<?php

namespace App\Console\Commands;

use App\Jobs\TestQueueJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:test {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test if the queue worker is processing jobs by dispatching a test job';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $message = $this->argument('message') ?? 'Test queue job dispatched at ' . now()->toDateTimeString();

        $this->info('Dispatching test queue job...');
        $this->info("Message: {$message}");

        // Dispatch the test job
        TestQueueJob::dispatch($message);

        $this->info('✅ Test job has been dispatched to the queue!');
        $this->newLine();

        // Show queue status
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->info('Queue Status:');
        $this->line("  Queue Connection: " . config('queue.default'));
        $this->line("  Pending Jobs: {$pendingJobs}");
        $this->line("  Failed Jobs: {$failedJobs}");
        $this->newLine();

        $this->info('📋 Next steps:');
        $this->line('  1. Check if the queue worker is running: php artisan queue:work');
        $this->line('  2. Check your logs for: "✅ QUEUE WORKER IS WORKING!"');
        $this->line('  3. Check the jobs table - the job should be removed after processing');
        $this->line('  4. If the job failed, check the failed_jobs table');

        return Command::SUCCESS;
    }
}
