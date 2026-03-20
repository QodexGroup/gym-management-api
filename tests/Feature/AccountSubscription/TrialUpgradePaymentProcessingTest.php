<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Helpers\GenericData;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Models\Account\AccountSubscriptionPlan;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

class TrialUpgradePaymentProcessingTest extends AccountSubscriptionFlowTestCase
{
    public function test_trial_upgrade_when_trial_already_ended_starts_now_and_generates_invoice_on_next_due_day(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 4, 19, 10, 0, 0));

        $account = $this->createAccount();
        $admin = $this->createUser($account);

        $monthlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);

        // Create a trial ASP where trial already ended.
        $trialAsp = AccountSubscriptionPlan::create([
            'account_id' => $account->id,
            'subscription_plan_id' => $this->trialPlan->id,
            'plan_name' => $this->trialPlan->name,
            'trial_starts_at' => Carbon::create(2026, 3, 5, 0, 0, 0),
            'trial_ends_at' => Carbon::create(2026, 3, 12, 0, 0, 0),
            'subscription_starts_at' => null,
            'subscription_ends_at' => null,
            'locked_at' => null,
        ]);

        $genericData = new GenericData();
        $genericData->userData = $admin;
        $genericData->data = [
            'subscriptionPlanId' => $monthlyPlan->id,
            'receiptUrl' => 'receipts/test-receipt.png',
            'receiptFileName' => 'test-receipt.png',
        ];
        $genericData->syncDataArray();

        $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $request = AccountPaymentRequest::query()
            ->where('account_id', $account->id)
            ->where('payment_transaction', AccountSubscriptionPlan::class)
            ->where('payment_transaction_id', $trialAsp->id)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->latest('id')
            ->first();

        $this->assertNotNull($request);

        $this->adminPaymentRequestService->approve($request->id, $admin->id);

        $trialAsp->refresh();
        $this->assertSame($monthlyPlan->id, $trialAsp->subscription_plan_id);
        $this->assertNull($trialAsp->trial_starts_at);
        $this->assertNull($trialAsp->trial_ends_at);
        $this->assertEquals(Carbon::create(2026, 4, 19, 0, 0, 0)->toDateString(), $trialAsp->subscription_starts_at?->toDateString());
        $this->assertEquals(Carbon::create(2026, 5, 19, 0, 0, 0)->toDateString(), $trialAsp->subscription_ends_at?->toDateString());

        // Invoice on Apr 5 should NOT be generated because subscription starts Apr 19.
        Carbon::setTestNow(Carbon::create(2026, 4, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $aprBillingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $aprInvoice = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $aprBillingPeriod)
            ->first();
        $this->assertNull($aprInvoice);

        // Invoice on May 5 should be generated.
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $mayBillingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $mayInvoice = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $mayBillingPeriod)
            ->first();

        $this->assertNotNull($mayInvoice);
        $this->assertSame(AccountInvoiceStatusConstant::STATUS_PENDING, $mayInvoice->status);
    }

    public function test_trial_upgrade_when_trial_still_active_starts_after_trial_end_plus_one_day(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 3, 10, 10, 0, 0));

        $account = $this->createAccount();
        $admin = $this->createUser($account);

        $monthlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);

        $trialAsp = AccountSubscriptionPlan::create([
            'account_id' => $account->id,
            'subscription_plan_id' => $this->trialPlan->id,
            'plan_name' => $this->trialPlan->name,
            'trial_starts_at' => Carbon::create(2026, 3, 5, 0, 0, 0),
            'trial_ends_at' => Carbon::create(2026, 3, 12, 0, 0, 0),
            'subscription_starts_at' => null,
            'subscription_ends_at' => null,
            'locked_at' => null,
        ]);

        $genericData = new GenericData();
        $genericData->userData = $admin;
        $genericData->data = [
            'subscriptionPlanId' => $monthlyPlan->id,
            'receiptUrl' => 'receipts/test-receipt.png',
            'receiptFileName' => 'test-receipt.png',
        ];
        $genericData->syncDataArray();

        $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $request = AccountPaymentRequest::query()
            ->where('account_id', $account->id)
            ->where('payment_transaction', AccountSubscriptionPlan::class)
            ->where('payment_transaction_id', $trialAsp->id)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->latest('id')
            ->first();

        $this->assertNotNull($request);

        $this->adminPaymentRequestService->approve($request->id, $admin->id);

        $trialAsp->refresh();
        $this->assertSame($monthlyPlan->id, $trialAsp->subscription_plan_id);

        // Trial end is Mar 12. We start Mar 13 at 00:00 on admin approval.
        $this->assertEquals(Carbon::create(2026, 3, 13, 0, 0, 0)->toDateString(), $trialAsp->subscription_starts_at?->toDateString());
        $this->assertEquals(Carbon::create(2026, 4, 13, 0, 0, 0)->toDateString(), $trialAsp->subscription_ends_at?->toDateString());

        // Invoice should not be generated on Mar 5, but should be on Apr 5 (subscription starts Mar 13).
        Carbon::setTestNow(Carbon::create(2026, 3, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $marchBillingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $marchInvoice = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $marchBillingPeriod)
            ->first();
        $this->assertNull($marchInvoice);

        Carbon::setTestNow(Carbon::create(2026, 4, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $aprilBillingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $aprilInvoice = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $aprilBillingPeriod)
            ->first();
        $this->assertNotNull($aprilInvoice);
    }

    public function test_trial_upgrade_prorates_remaining_days_at_next_due_day(): void
    {
        Queue::fake();

        $account = $this->createAccount();
        $admin = $this->createUser($account);

        $monthlyPlanPrice = 1200.00;
        $monthlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => $monthlyPlanPrice,
        ]);

        // Create a trial ASP that is already ended (so upgrade starts at the owner's submit time).
        $trialAsp = AccountSubscriptionPlan::create([
            'account_id' => $account->id,
            'subscription_plan_id' => $this->trialPlan->id,
            'plan_name' => $this->trialPlan->name,
            'trial_starts_at' => Carbon::create(2026, 3, 5, 0, 0, 0),
            'trial_ends_at' => Carbon::create(2026, 3, 1, 0, 0, 0),
            'subscription_starts_at' => null,
            'subscription_ends_at' => null,
            'locked_at' => null,
        ]);

        // Admin approves upgrade on Apr 19.
        Carbon::setTestNow(Carbon::create(2026, 4, 19, 10, 0, 0));

        $genericData = new GenericData();
        $genericData->userData = $admin;
        $genericData->data = [
            'subscriptionPlanId' => $monthlyPlan->id,
            'receiptUrl' => 'receipts/test-receipt.png',
            'receiptFileName' => 'test-receipt.png',
        ];
        $genericData->syncDataArray();

        $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $request = AccountPaymentRequest::query()
            ->where('account_id', $account->id)
            ->where('payment_transaction', AccountSubscriptionPlan::class)
            ->where('payment_transaction_id', $trialAsp->id)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->latest('id')
            ->first();

        $this->assertNotNull($request);

        $this->adminPaymentRequestService->approve($request->id, $admin->id);

        $trialAsp->refresh();

        // Upgrade paid window:
        // subscription_starts_at = Apr 19 (owner submit time @ 00:00)
        // subscription_ends_at   = May 19 (exclusive boundary, so display end is May 18)
        $this->assertEquals(Carbon::create(2026, 4, 19, 0, 0, 0)->toDateString(), $trialAsp->subscription_starts_at?->toDateString());
        $this->assertEquals(Carbon::create(2026, 5, 19, 0, 0, 0)->toDateString(), $trialAsp->subscription_ends_at?->toDateString());

        // Next due-day invoice generation:
        // Billing cycles are anchored at the 5th, so May 5 cycle should invoice the remaining
        // days AFTER the paid window ends: May 19 -> Jun 5 (exclusive).
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $mayBillingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $invoice = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $mayBillingPeriod)
            ->first();

        $this->assertNotNull($invoice);

        // Expected remaining-days proration:
        // period: May 5 -> Jun 5 = 31 days
        // remaining: May 19 -> Jun 5 = 17 days
        $expectedTotal = round($monthlyPlanPrice * (17 / 31), 2);
        $this->assertEquals($expectedTotal, (float) $invoice->total_amount);
        $this->assertSame(1, (int) $invoice->prorate);
    }
}

