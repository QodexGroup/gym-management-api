<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;

class AdminPaymentRejectionTest extends AccountSubscriptionFlowTestCase
{
    public function test_admin_rejects_pending_payment_request(): void
    {
        $account = $this->createAccount();
        $admin = $this->createUser($account);
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH]);
        $asp = $this->createAccountSubscriptionPlan($account, $plan);
        $invoice = $this->createInvoice($account, $asp);

        $request = $this->createPaymentRequest($account, $admin, $invoice);

        $rejected = $this->adminPaymentRequestService->reject($request->id, $admin->id, 'Blurry receipt');

        $this->assertSame(AccountPaymentRequestStatusConstant::STATUS_REJECTED, $rejected->status);
        $this->assertSame($admin->id, $rejected->approved_by);
        $this->assertNotNull($rejected->approved_at);
        $this->assertSame('Blurry receipt', $rejected->rejection_reason);
    }
}
