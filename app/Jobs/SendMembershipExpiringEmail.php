<?php

namespace App\Jobs;

use App\Mail\MembershipExpiringMail;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMembershipExpiringEmail implements ShouldQueue
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
        public int $customerId,
        public int $membershipId
    ) {
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new RateLimited('emails')];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customer = Customer::find($this->customerId);
        $membership = CustomerMembership::find($this->membershipId);
        if (!$customer || !$membership) {
            return;
        }
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return;
        }

        try {
            Mail::to($customer->email)->send(new MembershipExpiringMail($customer, $membership));

            Log::info('Membership expiring email sent', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'job_id' => $this->job->getJobId()
            ]);
        } catch (\Throwable $th) {
            Log::error('Error sending membership expiring email', [
                'error' => $th->getMessage(),
                'customer_id' => $customer->id,
                'job_id' => $this->job->getJobId()
            ]);

            throw $th;
        }
    }
}
