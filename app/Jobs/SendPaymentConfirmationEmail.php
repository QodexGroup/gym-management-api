<?php

namespace App\Jobs;

use App\Mail\PaymentConfirmationMail;
use App\Models\Core\Customer;
use App\Models\Core\CustomerPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentConfirmationEmail extends BaseEmailJob
{
    public function __construct(
        public int $customerId,
        public int $paymentId
    ) {}

    protected function execute(): void
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

        $this->recipient = $customer->email;

        Mail::to($customer->email)->send(new PaymentConfirmationMail($customer, $payment));

        Log::info('Payment confirmation email sent', [
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * The member this receipt was for, looked up again because failed() runs
     * on a job rebuilt from the queue payload.
     *
     * @return string|null the address, or null when it no longer resolves
     */
    protected function resolveRecipient(): ?string
    {
        return Customer::find($this->customerId)?->email;
    }
}
