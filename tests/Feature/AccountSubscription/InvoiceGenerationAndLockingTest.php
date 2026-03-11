<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountInvoiceTypeConstant;
use App\Constant\AccountStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\BillingCycleConstant;
use App\Jobs\SendAccountInvoiceNotificationJob;
use App\Models\Account\AccountInvoice;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

class InvoiceGenerationAndLockingTest extends AccountSubscriptionFlowTestCase
{
    public function test_generate_invoices_creates_pending_invoice_on_due_day(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 3, 5, 6, 0, 0));

        $account = $this->createAccount();
        $plan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);
        $asp = $this->createAccountSubscriptionPlan($account, $plan, [
            'subscription_starts_at' => Carbon::now()->copy()->subDays(10),
            'subscription_ends_at' => null,
        ]);

        $count = $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $this->assertSame(1, $count);

        $billingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $invoice = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $billingPeriod)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(AccountInvoiceStatusConstant::STATUS_PENDING, $invoice->status);
        $this->assertSame(0, (int) $invoice->prorate);

        $cycleStart = Carbon::now()->copy()->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
        $cycleEndExclusive = BillingLifecycleService::nextCycleStart($cycleStart->copy(), AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $cycleEndInclusive = $cycleEndExclusive->copy()->subDay();

        $this->assertEquals($cycleStart->toDateString(), $invoice->period_from->toDateString());
        $this->assertEquals($cycleEndInclusive->toDateString(), $invoice->period_to->toDateString());

        $details = $this->decodeJson($invoice->invoice_details);
        $this->assertSame(AccountInvoiceTypeConstant::TYPE_SUBSCRIPTION, $details['invoiceType'] ?? null);
        $this->assertSame($plan->id, $details['subscriptionPlan']['id'] ?? null);
        $this->assertSame($asp->id, $details['accountSubscriptionPlan']['id'] ?? null);

        Queue::assertPushed(SendAccountInvoiceNotificationJob::class, 1);
    }

    public function test_lock_accounts_marks_active_accounts_as_deactivated_when_unpaid(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 3, 10, 6, 0, 0));

        $account = $this->createAccount(['status' => AccountStatusConstant::STATUS_ACTIVE]);
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH]);
        $asp = $this->createAccountSubscriptionPlan($account, $plan);

        $billingPeriod = BillingLifecycleService::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $this->createInvoice($account, $asp, [
            'billing_period' => $billingPeriod,
            'status' => AccountInvoiceStatusConstant::STATUS_PENDING,
            'invoice_details' => [
                'invoiceType' => AccountInvoiceTypeConstant::TYPE_SUBSCRIPTION,
            ],
        ]);

        $count = $this->billingLifecycleService->lockAccountsWithUnpaidInvoiceForCurrentPeriod();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => AccountStatusConstant::STATUS_DEACTIVATED,
        ]);
        Queue::assertPushed(SendAccountInvoiceNotificationJob::class, 1);
    }
}
