<?php

namespace Tests\Feature;

use App\Jobs\TestQueueJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Support\CommandTestCase;

class TestQueueCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $jobsQuery = Mockery::mock();
        $jobsQuery->shouldReceive('count')->andReturn(0);

        $failedJobsQuery = Mockery::mock();
        $failedJobsQuery->shouldReceive('count')->andReturn(0);

        DB::shouldReceive('table')->with('jobs')->andReturn($jobsQuery);
        DB::shouldReceive('table')->with('failed_jobs')->andReturn($failedJobsQuery);
    }

    public function test_queue_test_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('queue:test', ['message' => 'hello queue'])
            ->assertSuccessful();

        Queue::assertPushed(TestQueueJob::class, function (TestQueueJob $job) {
            return $job->testMessage === 'hello queue';
        });
    }

    public function test_queue_test_sync_processes_without_queue(): void
    {
        Queue::fake();

        $this->artisan('queue:test', [
            'message' => 'sync hello',
            '--sync' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
