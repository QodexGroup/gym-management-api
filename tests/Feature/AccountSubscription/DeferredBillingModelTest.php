<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountSubscriptionIntervalConstant;
use App\Models\Account\AccountInvoice;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * Covers the deferred + full-period + pro-rated-bridge billing model.
 * Expected numbers were cross-checked with an independent date/proration model.
 */
class DeferredBillingModelTest extends AccountSubscriptionFlowTestCase
{
    private function invoiceForNow(int $accountId): ?AccountInvoice
    {
        return AccountInvoice::where('account_id', $accountId)
            ->where('billing_period', BillingLifecycleService::billingPeriodForDate(Carbon::now()))
            ->first();
    }

    /** Scenario A (defer): still prepaid on the Aug 5 run → no invoice. */
    public function test_defers_generation_while_prepaid(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 6, 0, 0));

        $account = $this->createAccount();
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH, 'price' => 800]);
        $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => Carbon::create(2026, 7, 16),
            'subscription_ends_at' => Carbon::create(2026, 8, 16), // exclusive
        ]);

        $count = $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('account_invoices', 0);
    }

    /** Scenario A (bill): Sep 5 run → full month + 20-day bridge = 1333.33. */
    public function test_bills_full_month_plus_bridge_after_coverage_ends(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 9, 5, 6, 0, 0));

        $account = $this->createAccount();
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH, 'price' => 800]);
        $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => Carbon::create(2026, 7, 16),
            'subscription_ends_at' => Carbon::create(2026, 8, 16),
        ]);

        $count = $this->billingLifecycleService->generateInvoicesForCurrentCycle();
        $this->assertSame(1, $count);

        $invoice = $this->invoiceForNow($account->id);
        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(1333.33, (float) $invoice->total_amount, 0.001);
        $this->assertSame(1, (int) $invoice->prorate);
        $this->assertEquals('2026-09-05', $invoice->period_from->toDateString());
        $this->assertEquals('2026-10-04', $invoice->period_to->toDateString());

        $details = $this->decodeJson($invoice->invoice_details);
        $this->assertEqualsWithDelta(800.0, (float) $details['fullPeriod']['amount'], 0.001);
        $this->assertSame(20, $details['bridge']['days']);
        $this->assertEqualsWithDelta(533.33, (float) $details['bridge']['amount'], 0.001);
    }

    /** Scenario A′: unpaid invoice must not stack a second invoice next cycle. */
    public function test_does_not_stack_new_invoice_while_previous_is_unpaid(): void
    {
        Queue::fake();

        $account = $this->createAccount();
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH, 'price' => 800]);
        $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => Carbon::create(2026, 7, 16),
            'subscription_ends_at' => Carbon::create(2026, 8, 16),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 5, 6, 0, 0));
        $this->assertSame(1, $this->billingLifecycleService->generateInvoicesForCurrentCycle());

        // Next cycle, invoice still unpaid → no new invoice.
        Carbon::setTestNow(Carbon::create(2026, 10, 5, 6, 0, 0));
        $this->assertSame(0, $this->billingLifecycleService->generateInvoicesForCurrentCycle());
        $this->assertDatabaseCount('account_invoices', 1);
    }

    /** Scenario C/G: quarterly bills a full quarter + bridge on its own cycle. */
    public function test_quarterly_bills_full_quarter_plus_bridge(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 6, 0, 0));

        $account = $this->createAccount();
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_QUARTER, 'price' => 2300]);
        $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => Carbon::create(2026, 8, 2),
            'subscription_ends_at' => Carbon::create(2026, 11, 2),
        ]);

        $this->assertSame(1, $this->billingLifecycleService->generateInvoicesForCurrentCycle());

        $invoice = $this->invoiceForNow($account->id);
        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(2375.00, (float) $invoice->total_amount, 0.001);
        $this->assertEquals('2026-11-05', $invoice->period_from->toDateString());
        $this->assertEquals('2027-02-04', $invoice->period_to->toDateString());
        $details = $this->decodeJson($invoice->invoice_details);
        $this->assertSame(3, $details['bridge']['days']);
        $this->assertEqualsWithDelta(75.00, (float) $details['bridge']['amount'], 0.001);
    }

    /** Edge: bridge spanning leap-year February. */
    public function test_bridge_across_leap_february(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2028, 3, 5, 6, 0, 0));

        $account = $this->createAccount();
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH, 'price' => 800]);
        $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => Carbon::create(2028, 1, 10),
            'subscription_ends_at' => Carbon::create(2028, 2, 20),
        ]);

        $this->assertSame(1, $this->billingLifecycleService->generateInvoicesForCurrentCycle());

        $invoice = $this->invoiceForNow($account->id);
        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(1161.29, (float) $invoice->total_amount, 0.001);
        $details = $this->decodeJson($invoice->invoice_details);
        $this->assertSame(14, $details['bridge']['days']);
        $this->assertEqualsWithDelta(361.29, (float) $details['bridge']['amount'], 0.001);
    }
}
