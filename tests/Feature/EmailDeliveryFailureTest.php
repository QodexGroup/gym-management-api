<?php

namespace Tests\Feature;

use App\Constant\NotificationConstant;
use App\Jobs\SendMembershipExpiringEmail;
use App\Mail\MembershipExpiringMail;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Core\NotificationEmailLog;
use App\Repositories\Core\NotificationEmailLogRepository;
use App\Services\Core\NotificationService;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\TestCase;

/**
 * Covers what happens when the mail provider rejects a send.
 *
 * The case that prompted these: Brevo answers 401 when the sending IP is not
 * on its authorised list. That used to leave the dedupe row claiming the member
 * had been emailed, so the reminder was never sent and nobody found out.
 */
class EmailDeliveryFailureTest extends TestCase
{
    protected Customer $customer;
    protected CustomerMembership $membership;
    protected NotificationEmailLogRepository $emailLogs;

    protected function setUp(): void
    {
        parent::setUp();

        // The email jobs carry a RateLimited('emails') middleware capped at six
        // a minute. Left in place it would release these test dispatches back
        // onto the queue instead of running them.
        RateLimiter::for('emails', fn () => Limit::none());

        $this->emailLogs = app(NotificationEmailLogRepository::class);

        $plan = MembershipPlan::create([
            'account_id' => 1,
            'plan_name' => 'Monthly Plan',
            'price' => 1000.00,
            'plan_period' => 1,
            'plan_interval' => 'months',
            'features' => [],
        ]);

        $this->customer = Customer::create([
            'account_id' => 1,
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@example.com',
            'phone_number' => '09170000000',
            'balance' => 0,
        ]);

        $this->membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $plan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => Carbon::now()->addDays(7),
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Build the exception Symfony's Brevo transport actually throws. The
     * exception code is 0 and the HTTP status appears only on the response and
     * in the message text, which is what the classification has to cope with.
     */
    private function transportFailure(int $status, bool $responseReadable = true): HttpTransportException
    {
        $response = Mockery::mock(ResponseInterface::class);

        if ($responseReadable) {
            $response->shouldReceive('getStatusCode')->andReturn($status);
        } else {
            $response->shouldReceive('getStatusCode')
                ->andThrow(new \RuntimeException('the response never resolved'));
        }

        return new HttpTransportException(
            sprintf('Unable to send an email: provider rejected the request (code %d).', $status),
            $response
        );
    }

    private function mailFailsWith(\Throwable $th): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow($th);
    }

    private function job(?int $emailLogId = null): SendMembershipExpiringEmail
    {
        return new SendMembershipExpiringEmail($this->customer->id, $this->membership->id, $emailLogId);
    }

    private function reserveLogRow(): NotificationEmailLog
    {
        return $this->emailLogs->log(
            1,
            NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
            $this->customer->id,
            $this->membership->id
        );
    }

    // ------------------------------------------------- classification of errors

    /**
     * The reason this change exists. Brevo returns 401 both for a key it does
     * not recognise and for an unauthorised sending IP, and the second is
     * fixed by a person in minutes, well inside the backoff ladder. Failing
     * fast here would throw away an email a retry would deliver - and the
     * payment, registration and invoice jobs have no second chance at all.
     */
    public function test_an_unauthorised_ip_is_retried_rather_than_discarded(): void
    {
        $this->mailFailsWith($this->transportFailure(401));

        $this->expectException(HttpTransportException::class);

        $this->job()->handle();
    }

    public function test_a_rate_limit_is_retried(): void
    {
        $this->mailFailsWith($this->transportFailure(429));

        $this->expectException(HttpTransportException::class);

        $this->job()->handle();
    }

    public function test_a_provider_server_error_is_retried(): void
    {
        $this->mailFailsWith($this->transportFailure(503));

        $this->expectException(HttpTransportException::class);

        $this->job()->handle();
    }

    /**
     * An error carrying no provider status at all (an SMTP fault, a bug of
     * ours) must be treated as transient rather than silently dropping mail.
     */
    public function test_an_error_without_a_provider_status_is_retried(): void
    {
        $this->mailFailsWith(new \RuntimeException('connection reset by peer'));

        $this->expectException(\RuntimeException::class);

        $this->job()->handle();
    }

    /**
     * A rejected message is the one thing retrying cannot fix, so the job is
     * failed on the first attempt instead of waiting out the whole ladder.
     */
    public function test_a_rejected_message_fails_the_job_immediately(): void
    {
        $this->mailFailsWith($this->transportFailure(422));

        $job = $this->job()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(HttpTransportException::class);
    }

    public function test_a_malformed_request_fails_the_job_immediately(): void
    {
        $this->mailFailsWith($this->transportFailure(400));

        $job = $this->job()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(HttpTransportException::class);
    }

    /**
     * Brevo's transport also throws when it cannot parse an otherwise
     * successful response. The message was accepted, so a retry would deliver
     * a second copy.
     */
    public function test_an_accepted_message_is_never_retried(): void
    {
        $this->mailFailsWith($this->transportFailure(201));

        $job = $this->job()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(HttpTransportException::class);
    }

    /**
     * When the transport could not reach the provider there is no status on the
     * response, so it has to be read out of the message text instead.
     */
    public function test_the_status_is_read_from_the_message_when_the_response_cannot_be_read(): void
    {
        $this->mailFailsWith($this->transportFailure(422, responseReadable: false));

        $job = $this->job()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(HttpTransportException::class);
    }

    // ------------------------------------------------ settling the dedupe row

    public function test_a_successful_send_marks_the_dedupe_row_sent(): void
    {
        Mail::fake();

        $log = $this->reserveLogRow();

        SendMembershipExpiringEmail::dispatch($this->customer->id, $this->membership->id, $log->id);

        $log->refresh();

        $this->assertSame(NotificationEmailLog::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
        Mail::assertSent(MembershipExpiringMail::class);
    }

    public function test_giving_up_releases_the_dedupe_row(): void
    {
        $this->mailFailsWith($this->transportFailure(422));

        $log = $this->reserveLogRow();

        SendMembershipExpiringEmail::dispatch($this->customer->id, $this->membership->id, $log->id);

        $log->refresh();

        $this->assertSame(NotificationEmailLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('provider rejected', (string) $log->failed_reason);
    }

    public function test_a_vanished_customer_releases_the_dedupe_row(): void
    {
        Mail::fake();

        $log = $this->reserveLogRow();

        SendMembershipExpiringEmail::dispatch($this->customer->id + 9999, $this->membership->id, $log->id);

        $this->assertSame(NotificationEmailLog::STATUS_FAILED, $log->refresh()->status);
        Mail::assertNothingSent();
    }

    /**
     * A worker killed between the send and the bookkeeping leaves the queue row
     * to be reclaimed and the job re-run. It must not email the member twice.
     */
    public function test_a_row_already_sent_is_not_emailed_again(): void
    {
        Mail::fake();

        $log = $this->reserveLogRow();
        $this->emailLogs->markSent($log->id);

        $this->job($log->id)->handle();

        Mail::assertNothingSent();
    }

    /**
     * Regression guard. A payload serialised before emailLogId existed restores
     * that promoted property uninitialised, not null, so reading it directly
     * threw an Error - and it threw AFTER the email had gone out, so every
     * retry sent the member another copy.
     */
    public function test_a_payload_queued_before_this_change_still_sends_exactly_once(): void
    {
        Mail::fake();

        $job = (new \ReflectionClass(SendMembershipExpiringEmail::class))->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass($job);
        $reflection->getProperty('customerId')->setValue($job, $this->customer->id);
        $reflection->getProperty('membershipId')->setValue($job, $this->membership->id);

        // Exactly the state unserialize() leaves behind for the new parameter.
        $this->assertFalse(isset($job->emailLogId), 'the old payload must leave this property unset');

        $job->handle();

        Mail::assertSent(MembershipExpiringMail::class);
        $this->assertCount(1, Mail::sent(MembershipExpiringMail::class));
    }

    // ----------------------------------------------------- dedupe consequences

    /**
     * The whole point of the status column: a reminder that failed must not
     * keep the member out of the next run.
     */
    public function test_a_failed_row_does_not_suppress_the_next_reminder(): void
    {
        $log = $this->reserveLogRow();

        $this->assertTrue(
            $this->emailLogs->emailExists(1, NotificationConstant::TYPE_MEMBERSHIP_EXPIRING, $this->customer->id, $this->membership->id),
            'a row still queued should suppress a duplicate while the job is in flight'
        );

        $this->emailLogs->markFailed($log->id, 'provider rejected the sending IP');

        $this->assertFalse(
            $this->emailLogs->emailExists(1, NotificationConstant::TYPE_MEMBERSHIP_EXPIRING, $this->customer->id, $this->membership->id),
            'a failed row must leave the member eligible for another attempt'
        );
    }

    public function test_a_sent_row_still_suppresses_a_duplicate(): void
    {
        $log = $this->reserveLogRow();
        $this->emailLogs->markSent($log->id);

        $this->assertTrue(
            $this->emailLogs->emailExists(1, NotificationConstant::TYPE_MEMBERSHIP_EXPIRING, $this->customer->id, $this->membership->id)
        );
    }

    /**
     * The row has to exist before the job is queued, otherwise two scheduler
     * runs five minutes apart both dispatch and the member is emailed twice.
     */
    public function test_the_reminder_reserves_a_queued_row_before_dispatching(): void
    {
        Queue::fake();

        app(NotificationService::class)->sendMembershipExpiringEmail($this->customer, $this->membership, 1);

        $log = NotificationEmailLog::where('customer_id', $this->customer->id)->sole();

        $this->assertSame(NotificationEmailLog::STATUS_QUEUED, $log->status);
        $this->assertNull($log->sent_at);

        Queue::assertPushed(
            SendMembershipExpiringEmail::class,
            fn (SendMembershipExpiringEmail $job) => $job->emailLogId === $log->id
        );
    }
}
