<?php

namespace App\Jobs;

use App\Mail\MembershipExpiringMail;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMembershipExpiringEmail extends BaseEmailJob
{
    public function __construct(
        public int $customerId,
        public int $membershipId
    ) {}

    protected function execute(): void
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

        Mail::to($customer->email)->send(new MembershipExpiringMail($customer, $membership));

        Log::info('Membership expiring email sent', [
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'membership_id' => $membership->id,
        ]);
    }
}
