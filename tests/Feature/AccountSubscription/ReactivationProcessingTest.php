<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\AccountInvoiceTypeConstant;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;

class ReactivationProcessingTest extends AccountSubscriptionFlowTestCase
{
    public function test_process_reactivation_applies_free_month_and_switches_to_monthly(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 10, 8, 0, 0));

        $account = $this->createAccount(['status' => AccountStatusConstant::STATUS_DEACTIVATED]);
        $admin = $this->createUser($account);

        $monthlyPlan = $this->createPlan([
            'name' => 'Monthly Paid',
            'slug' => 'monthly-low',
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 10,
        ]);
        $quarterlyPlan = $this->createPlan([
            'name' => 'Quarterly Paid',
            'slug' => 'quarterly-test',
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_QUARTER,
            'price' => 300,
        ]);

        $asp = $this->createAccountSubscriptionPlan($account, $quarterlyPlan, [
            'subscription_starts_at' => Carbon::now()->copy()->subMonths(2),
            'subscription_ends_at' => Carbon::now()->copy()->subMonth(),
        ]);

        $reactivationInvoice = $this->createInvoice($account, $asp, [
            'status' => AccountInvoiceStatusConstant::STATUS_PENDING,
            'invoice_details' => $this->buildReactivationInvoiceDetails(),
        ]);

        $otherInvoice = $this->createInvoice($account, $asp, [
            'billing_period' => '02052026',
            'status' => AccountInvoiceStatusConstant::STATUS_PENDING,
            'invoice_details' => [
                'invoiceType' => AccountInvoiceTypeConstant::TYPE_SUBSCRIPTION,
            ],
        ]);

        $request = $this->createPaymentRequest($account, $admin, $reactivationInvoice, [
            'status' => AccountPaymentRequestStatusConstant::STATUS_APPROVED,
            'approved_at' => Carbon::now(),
        ]);

        $processed = $this->adminPaymentRequestService->processApprovedReactivations($account->id);

        $this->assertSame(1, $processed);

        $account->refresh();
        $asp->refresh();
        $otherInvoice->refresh();
        $request->refresh();

        $this->assertSame(AccountStatusConstant::STATUS_ACTIVE, $account->status);
        $this->assertSame($monthlyPlan->id, $asp->subscription_plan_id);

        $nextCycleStart = Carbon::create(2026, 4, 5)->startOfDay();
        $expectedEnd = BillingLifecycleService::nextCycleStart(
            $nextCycleStart->copy(),
            AccountSubscriptionIntervalConstant::INTERVAL_MONTH
        )->addMonthNoOverflow();

        $this->assertEquals($nextCycleStart->toDateString(), $asp->subscription_starts_at?->toDateString());
        $this->assertEquals($expectedEnd->toDateString(), $asp->subscription_ends_at?->toDateString());
        $this->assertSame(AccountInvoiceStatusConstant::STATUS_VOID, $otherInvoice->status);

        $details = $this->decodeJson($request->payment_details);
        $this->assertTrue($details['reactivationProcessed'] ?? false);
        $this->assertSame(AccountInvoiceTypeConstant::TYPE_REACTIVATION_FEE, $details['reactivation']['invoiceType'] ?? null);
        $this->assertSame($nextCycleStart->toDateString(), $details['reactivation']['subscriptionStartsAt'] ?? null);
    }
}
