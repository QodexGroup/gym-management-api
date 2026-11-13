<?php

namespace App\Jobs;

use App\Mail\MembershipExpiringMail;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\NotificationEmailLogRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMembershipExpiringEmail extends BaseEmailJob
{
    /**
     * @param int $customerId
     * @param int $membershipId
     * @param int|null $emailLogId dedupe row reserved before dispatch, which
     *                             this job settles so a failed send does not
     *                             suppress the next reminder run
     */
    public function __construct(
        public int $customerId,
        public int $membershipId,
        public ?int $emailLogId = null
    ) {}

    protected function execute(): void
    {
        $logId = $this->reservedLogId();

        // A worker killed between the send and the bookkeeping below leaves the
        // queue row to be reclaimed and the job run again from the top. Without
        // this check that second run sends the member a duplicate.
        if ($logId !== null && app(NotificationEmailLogRepository::class)->isSent($logId)) {
            Log::info('Membership expiring email already delivered; skipping duplicate', [
                'customer_id' => $this->customerId,
                'membership_id' => $this->membershipId,
                'email_log_id' => $logId,
            ]);

            return;
        }

        $customer = Customer::find($this->customerId);
        $membership = CustomerMembership::find($this->membershipId);
        if (!$customer || !$membership) {
            $this->settleEmailLogAsFailed('Customer or membership no longer exists');
            return;
        }
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            $this->settleEmailLogAsFailed('Customer has no email address');
            return;
        }

        $this->recipient = $customer->email;

        Mail::to($customer->email)->send(new MembershipExpiringMail($customer, $membership));

        $this->settleEmailLogAsSent();

        Log::info('Membership expiring email sent', [
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'membership_id' => $membership->id,
        ]);
    }

    /**
     * The reminder is lost, so release the dedupe row: the next scheduler run
     * must be free to try this member again.
     */
    protected function onGaveUp(?\Throwable $th): void
    {
        $this->settleEmailLogAsFailed($th?->getMessage() ?? 'Email job gave up');
    }

    protected function resolveRecipient(): ?string
    {
        return Customer::find($this->customerId)?->email;
    }

    /**
     * The reserved dedupe row, or null when this job was queued before the row
     * was threaded through it.
     *
     * A promoted constructor property carries no property-level default, so a
     * payload serialised by the two-argument version of this class - one still
     * sitting in the jobs table across a deploy - restores this property
     * uninitialised rather than null. Reading it directly would throw an Error
     * after the email had already gone out, and the retry would send a
     * duplicate. The null coalesce is safe where a plain read is not.
     */
    private function reservedLogId(): ?int
    {
        return $this->emailLogId ?? null;
    }

    private function settleEmailLogAsSent(): void
    {
        $logId = $this->reservedLogId();
        if ($logId === null) {
            return;
        }

        try {
            app(NotificationEmailLogRepository::class)->markSent($logId);
        } catch (\Throwable $th) {
            // The email is already delivered. A failed bookkeeping write must
            // never bubble out of here, because handle() would read it as a
            // transient fault and the retry would send a second copy. At worst
            // the row ages out of the dedupe window on its own.
            Log::error('Email was sent but the notification email log could not be updated', [
                'email_log_id' => $logId,
                'error' => $th->getMessage(),
            ]);
        }
    }

    private function settleEmailLogAsFailed(?string $reason): void
    {
        $logId = $this->reservedLogId();
        if ($logId === null) {
            return;
        }

        try {
            app(NotificationEmailLogRepository::class)->markFailed($logId, $reason);
        } catch (\Throwable $th) {
            // This runs on the failure path, where the database may be what
            // broke. A throw here would replace the real failure report.
            Log::error('Could not release notification email log row', [
                'email_log_id' => $logId,
                'error' => $th->getMessage(),
            ]);
        }
    }
}
