<?php

namespace App\Jobs;

use App\Mail\CustomerRegistrationMail;
use App\Models\Core\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCustomerRegistrationEmail extends BaseEmailJob
{
    public function __construct(
        public int $customerId
    ) {}

    protected function execute(): void
    {
        $customer = Customer::find($this->customerId);
        if (!$customer) {
            return;
        }
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return;
        }

        Mail::to($customer->email)->send(new CustomerRegistrationMail($customer));

        Log::info('Customer registration email sent', [
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);
    }
}
