<?php

namespace App\Jobs;

use App\Mail\PaymentConfirmationMail;
use App\Models\Core\Customer;
use App\Models\Core\CustomerPayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentConfirmationEmail implements ShouldQueue
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
        public int $paymentId
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
        $payment = CustomerPayment::find($this->paymentId);
        if (!$customer || !$payment) {
            return;
        }
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return;
        }

        try {
            Mail::to($customer->email)->send(new PaymentConfirmationMail($customer, $payment));

            Log::info('Payment confirmation email sent', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'payment_id' => $payment->id,
                'job_id' => $this->job->getJobId()
            ]);
        } catch (\Throwable $th) {
            Log::error('Error sending payment confirmation email', [
                'error' => $th->getMessage(),
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'job_id' => $this->job->getJobId()
            ]);

            throw $th;
        }
    }
}
