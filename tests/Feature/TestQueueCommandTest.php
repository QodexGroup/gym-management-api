<?php

namespace Tests\Feature;

use App\Jobs\TestQueueJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TestQueueCommandTest extends TestCase
{
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
