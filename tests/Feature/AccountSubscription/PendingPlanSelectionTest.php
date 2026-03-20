<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountSubscriptionIntervalConstant;
use App\Helpers\GenericData;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Models\Account\AccountSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

class PendingPlanSelectionTest extends AccountSubscriptionFlowTestCase
{
    public function test_active_plan_change_is_stored_as_pending_and_applies_on_next_due_day(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 4, 20, 10, 0, 0));

        $account = $this->createAccount();
        $owner = $this->createUser($account);

        $currentPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);

        $nextPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_QUARTER,
            'price' => 3000,
        ]);

        $asp = AccountSubscriptionPlan::create([
            'account_id' => $account->id,
            'subscription_plan_id' => $currentPlan->id,
            'plan_name' => $currentPlan->name,
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'subscription_starts_at' => Carbon::create(2026, 4, 5, 0, 0, 0),
            'subscription_ends_at' => null,
            'locked_at' => null,
        ]);

        $genericData = new GenericData();
        $genericData->userData = $owner;
        $genericData->data = [
            'subscriptionPlanId' => $nextPlan->id,
        ];
        $genericData->syncDataArray();

        $response = $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $asp->refresh();

        // Current active plan remains unchanged until next due day.
        $this->assertSame($currentPlan->id, (int) $asp->subscription_plan_id);
        $this->assertSame($nextPlan->id, (int) $asp->pending_subscription_plan_id);
        $this->assertEquals(
            Carbon::create(2026, 5, 5, 0, 0, 0)->toDateString(),
            $asp->pending_plan_effective_at?->toDateString()
        );
        $this->assertNotNull($response['nextBillingDate']);

        // On due day, pending plan should be applied before invoice generation.
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $asp->refresh();
        $this->assertSame($nextPlan->id, (int) $asp->subscription_plan_id);
        $this->assertNull($asp->pending_subscription_plan_id);
        $this->assertNull($asp->pending_plan_effective_at);

        $invoice = AccountInvoice::query()
            ->where('account_id', $account->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals((float) $nextPlan->price, (float) $invoice->total_amount);
    }

    public function test_active_plan_change_does_not_create_payment_request_and_invoice_uses_applied_pending_plan(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 4, 21, 9, 0, 0));

        $account = $this->createAccount();
        $owner = $this->createUser($account);

        $monthlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);

        $quarterlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_QUARTER,
            'price' => 3300,
        ]);

        $asp = AccountSubscriptionPlan::create([
            'account_id' => $account->id,
            'subscription_plan_id' => $monthlyPlan->id,
            'plan_name' => $monthlyPlan->name,
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'subscription_starts_at' => Carbon::create(2026, 4, 5, 0, 0, 0),
            'subscription_ends_at' => null,
            'locked_at' => null,
        ]);

        $genericData = new GenericData();
        $genericData->userData = $owner;
        $genericData->data = [
            'subscriptionPlanId' => $quarterlyPlan->id,
        ];
        $genericData->syncDataArray();

        $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        // Active paid-to-paid change should not create any payment request.
        $paymentRequestsCount = AccountPaymentRequest::query()
            ->where('account_id', $account->id)
            ->count();
        $this->assertSame(0, $paymentRequestsCount);

        $asp->refresh();
        $this->assertSame($monthlyPlan->id, (int) $asp->subscription_plan_id);
        $this->assertSame($quarterlyPlan->id, (int) $asp->pending_subscription_plan_id);

        Carbon::setTestNow(Carbon::create(2026, 5, 5, 6, 0, 0));
        $this->billingLifecycleService->generateInvoicesForCurrentCycle();

        $invoice = AccountInvoice::query()
            ->where('account_id', $account->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($invoice);

        $details = $this->decodeJson($invoice->invoice_details);
        $this->assertSame($quarterlyPlan->id, (int) ($details['subscriptionPlan']['id'] ?? 0));
        $this->assertSame(AccountSubscriptionIntervalConstant::INTERVAL_QUARTER, (string) ($details['subscriptionPlan']['interval'] ?? ''));
        $this->assertEquals((float) $quarterlyPlan->price, (float) $invoice->total_amount);
    }

    public function test_quarterly_to_monthly_change_with_june_end_applies_on_july_5(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 6, 20, 10, 0, 0));

        $account = $this->createAccount();
        $owner = $this->createUser($account);

        $quarterlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_QUARTER,
            'price' => 3300,
        ]);

        $monthlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);

        $asp = AccountSubscriptionPlan::create([
            'account_id' => $account->id,
            'subscription_plan_id' => $quarterlyPlan->id,
            'plan_name' => $quarterlyPlan->name,
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'subscription_starts_at' => Carbon::create(2026, 4, 5, 0, 0, 0),
            'subscription_ends_at' => Carbon::create(2026, 6, 30, 0, 0, 0),
            'locked_at' => null,
        ]);

        $genericData = new GenericData();
        $genericData->userData = $owner;
        $genericData->data = [
            'subscriptionPlanId' => $monthlyPlan->id,
        ];
        $genericData->syncDataArray();

        $response = $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $asp->refresh();
        $this->assertSame($quarterlyPlan->id, (int) $asp->subscription_plan_id);
        $this->assertSame($monthlyPlan->id, (int) $asp->pending_subscription_plan_id);
        $this->assertEquals(
            Carbon::create(2026, 7, 5, 0, 0, 0)->toDateString(),
            $asp->pending_plan_effective_at?->toDateString()
        );
        $this->assertSame('Jul 05, 2026', $response['nextBillingDate'] ?? null);
    }
}

