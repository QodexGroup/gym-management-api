<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\BillingCycleConstant;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;

class AdminPaymentApprovalTest extends AccountSubscriptionFlowTestCase
{
    public function test_admin_approves_invoice_payment_and_activates_account(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 12, 9, 0, 0));

        $account = $this->createAccount(['status' => AccountStatusConstant::STATUS_DEACTIVATED]);
        $admin = $this->createUser($account);
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH]);
        $asp = $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => null,
            'subscription_ends_at' => null,
        ]);

        $invoice = $this->createInvoice($account, $asp, [
            'status' => AccountInvoiceStatusConstant::STATUS_PENDING,
            'invoice_details' => $this->buildSubscriptionInvoiceDetails($plan, $asp),
        ]);

        $request = $this->createPaymentRequest($account, $admin, $invoice, [
            'status' => AccountPaymentRequestStatusConstant::STATUS_PENDING,
        ]);

        $approved = $this->adminPaymentRequestService->approve($request->id, $admin->id);

        $invoice->refresh();
        $asp->refresh();
        $account->refresh();

        $this->assertSame(AccountInvoiceStatusConstant::STATUS_PAID, $invoice->status);
        $this->assertSame(AccountPaymentRequestStatusConstant::STATUS_APPROVED, $approved->status);
        $this->assertSame($admin->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(AccountStatusConstant::STATUS_ACTIVE, $account->status);

        $cycleStart = Carbon::now()->copy()->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
        $expectedEnd = BillingLifecycleService::nextCycleStart(
            $cycleStart->copy(),
            AccountSubscriptionIntervalConstant::INTERVAL_MONTH
        );

        $this->assertEquals($cycleStart->toDateString(), $asp->subscription_starts_at?->toDateString());
        $this->assertEquals($expectedEnd->toDateString(), $asp->subscription_ends_at?->toDateString());
    }
}
