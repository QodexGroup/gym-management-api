<?php

namespace Tests\Feature;

use App\Constant\NotificationConstant;
use App\Data\GeneratedReportFile;
use App\Helpers\GenericData;
use App\Jobs\SendReportEmail;
use App\Mail\ReportEmailMail;
use App\Models\Core\Notification;
use App\Models\User;
use App\Services\Account\ReportService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * The report email moved off the request and onto the queue.
 *
 * What matters here is that the request only ever queues work, that the file
 * the job renders is removed whatever the provider does with the message, and
 * that a report nobody could deliver still reaches the user as an in-app
 * notification - the email that would have told them is the thing that failed.
 */
class ReportEmailQueueTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'account_id' => 1,
            'firstname' => 'Report',
            'lastname' => 'Requester',
            'email' => 'requester@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Build the validated shape the controller hands the service.
     */
    private function genericData(array $overrides = []): GenericData
    {
        $genericData = new GenericData();
        $genericData->userData = $this->user;
        $genericData->data = array_merge([
            'reportType' => 'collection',
            'format' => 'pdf',
            'dateRange' => 'custom',
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
        ], $overrides);

        return $genericData;
    }

    /**
     * Put a real file on disk and make the report generator return it, so the
     * cleanup assertions are about a file that actually exists.
     */
    private function generatorReturnsFile(string $path): void
    {
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'report bytes');

        $report = new GeneratedReportFile();
        $report->filePath = $path;
        $report->title = 'Collection Report';

        $service = Mockery::mock(ReportService::class);
        $service->shouldReceive('generateEmailReportFile')->andReturn($report);
        $service->shouldReceive('emailReportFilePath')->andReturn($path);

        $this->app->instance(ReportService::class, $service);
    }

    private function tempReportPath(): string
    {
        return storage_path('app/reports/collection-report-2026-01-01.pdf');
    }

    // ------------------------------------------------- the request only queues

    public function test_requesting_a_report_queues_the_job_rather_than_sending_it(): void
    {
        Queue::fake();

        app(ReportService::class)->requestEmailReport($this->genericData());

        Queue::assertPushed(SendReportEmail::class, function (SendReportEmail $job) {
            return $job->userId === $this->user->id
                && $job->reportParams['reportType'] === 'collection'
                && $job->reportParams['format'] === 'pdf'
                && $job->reportParams['startDate'] === '2026-01-01'
                && $job->reportParams['endDate'] === '2026-12-31';
        });
    }

    /**
     * format is nullable on the request, so the job must receive a usable
     * default rather than null - it is passed straight to the mailable.
     */
    public function test_a_request_without_a_format_queues_a_pdf(): void
    {
        Queue::fake();

        $genericData = $this->genericData();
        unset($genericData->data['format']);

        app(ReportService::class)->requestEmailReport($genericData);

        Queue::assertPushed(SendReportEmail::class, fn (SendReportEmail $job) => $job->reportParams['format'] === 'pdf');
    }

    /**
     * Only primitives may cross into the payload. A serialised User would be
     * restored from a row that may have changed by the time the job runs.
     */
    public function test_the_payload_carries_no_models(): void
    {
        Queue::fake();

        app(ReportService::class)->requestEmailReport($this->genericData());

        Queue::assertPushed(SendReportEmail::class, function (SendReportEmail $job) {
            foreach ($job->reportParams as $value) {
                $this->assertTrue($value === null || is_scalar($value));
            }

            return is_int($job->userId);
        });
    }

    // ------------------------------------------------- the job's own behaviour

    public function test_a_successful_send_emails_the_report_and_removes_the_file(): void
    {
        Mail::fake();

        $path = $this->tempReportPath();
        $this->generatorReturnsFile($path);

        (new SendReportEmail($this->user->id, (array) $this->genericData()->getData()))->handle();

        Mail::assertSent(ReportEmailMail::class);
        $this->assertFileDoesNotExist($path, 'the generated report must not be left on disk');
    }

    /**
     * The cleanup sits in a finally, so a provider failure must not leak the
     * file. This is the case that leaked before the report email was queued.
     */
    public function test_a_provider_failure_still_removes_the_file(): void
    {
        $path = $this->tempReportPath();
        $this->generatorReturnsFile($path);

        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('provider rejected the message'));

        try {
            (new SendReportEmail($this->user->id, (array) $this->genericData()->getData()))->handle();
        } catch (\Throwable) {
            // The retry classification is BaseEmailJob's business and is
            // covered in EmailDeliveryFailureTest; here only the file matters.
        }

        $this->assertFileDoesNotExist($path, 'a failed send must not leave the report behind');
    }

    public function test_giving_up_tells_the_user_in_app(): void
    {
        $path = $this->tempReportPath();
        $this->generatorReturnsFile($path);

        (new SendReportEmail($this->user->id, (array) $this->genericData()->getData()))
            ->failed(new \RuntimeException('gave up'));

        $notification = Notification::where('type', NotificationConstant::TYPE_REPORT_EMAIL_FAILED)->first();

        $this->assertNotNull($notification, 'a report nobody could deliver must still reach the user');
        $this->assertSame($this->user->id, $notification->user_id);
        $this->assertSame($this->user->account_id, $notification->account_id);
        $this->assertFileDoesNotExist($path, 'giving up must clear anything an attempt left behind');
    }

    /**
     * failed() runs on a job rebuilt from the payload, so a user deleted in the
     * meantime must not turn a lost report into a second, noisier failure.
     */
    public function test_giving_up_on_a_vanished_user_does_not_throw(): void
    {
        $this->generatorReturnsFile($this->tempReportPath());

        $userId = $this->user->id;
        $this->user->delete();

        (new SendReportEmail($userId, (array) $this->genericData()->getData()))
            ->failed(new \RuntimeException('gave up'));

        $this->assertSame(0, Notification::where('type', NotificationConstant::TYPE_REPORT_EMAIL_FAILED)->count());
    }

    protected function tearDown(): void
    {
        $path = $this->tempReportPath();
        if (file_exists($path)) {
            @unlink($path);
        }

        parent::tearDown();
    }
}
