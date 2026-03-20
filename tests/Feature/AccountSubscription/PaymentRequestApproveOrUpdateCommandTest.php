<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountPaymentTypeConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Models\Account\AccountPaymentRequest;
use App\Models\Account\AccountSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PaymentRequestApproveOrUpdateCommandTest extends AccountSubscriptionFlowTestCase
{
    public function test_command_approves_pending_trial_upgrade_payment_request(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 19, 10, 0, 0));

        $account = $this->createAccount();
        $admin = $this->createUser($account);

        $monthlyPlan = $this->createPlan([
            'interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH,
            'price' => 1200,
        ]);

        // Trial ASP where trial is already ended -> subscription start should use requestedAt time (Apr 19).
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

        $genericData = new \App\Helpers\GenericData();
        $genericData->userData = $admin;
        $genericData->data = [
            'subscriptionPlanId' => $monthlyPlan->id,
            'paymentType' => AccountPaymentTypeConstant::GCASH,
            'receiptUrl' => 'receipts/test-receipt.png',
            'receiptFileName' => 'test-receipt.png',
        ];
        $genericData->syncDataArray();

        $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $paymentRequest = AccountPaymentRequest::query()
            ->where('account_id', $account->id)
            ->where('payment_transaction', \App\Models\Account\AccountSubscriptionPlan::class)
            ->where('payment_transaction_id', $trialAsp->id)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->latest('id')
            ->first();

        $this->assertNotNull($paymentRequest);

        $exitCode = Artisan::call('payment-request:approve-or-update', [
            'account_id' => $account->id,
        ]);

        $this->assertSame(0, $exitCode);

        $trialAsp->refresh();
        $paymentRequest->refresh();

        // subscription_starts_at should be Apr 19 00:00 based on requestedAt.
        $this->assertSame(
            Carbon::create(2026, 4, 19, 0, 0, 0)->toDateString(),
            $trialAsp->subscription_starts_at?->toDateString()
        );
        $this->assertSame(AccountPaymentRequestStatusConstant::STATUS_APPROVED, $paymentRequest->status);
        $this->assertEquals(Carbon::create(2026, 3, 5, 0, 0, 0)->toDateString(), $trialAsp->trial_starts_at?->toDateString());
        $this->assertEquals(Carbon::create(2026, 3, 1, 0, 0, 0)->toDateString(), $trialAsp->trial_ends_at?->toDateString());
    }

    public function test_command_processes_latest_approved_subscription_upgrade_when_no_pending(): void
    {
        // Create requestedAt at Apr 19, but run the command on a later day.
        Carbon::setTestNow(Carbon::create(2026, 4, 19, 9, 0, 0));

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
            'trial_ends_at' => Carbon::create(2026, 3, 1, 0, 0, 0),
            'subscription_starts_at' => null,
            'subscription_ends_at' => null,
            'locked_at' => null,
        ]);

        $genericData = new \App\Helpers\GenericData();
        $genericData->userData = $admin;
        $genericData->data = [
            'subscriptionPlanId' => $monthlyPlan->id,
            'paymentType' => AccountPaymentTypeConstant::GCASH,
            'receiptUrl' => 'receipts/test-receipt.png',
            'receiptFileName' => 'test-receipt.png',
        ];
        $genericData->syncDataArray();

        $this->accountPaymentRequestService->createSubscriptionRequest($genericData);

        $paymentRequest = AccountPaymentRequest::query()
            ->where('account_id', $account->id)
            ->where('payment_transaction', \App\Models\Account\AccountSubscriptionPlan::class)
            ->where('payment_transaction_id', $trialAsp->id)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->latest('id')
            ->first();

        $this->assertNotNull($paymentRequest);
        $paymentRequest->refresh();
        $this->assertNotNull($paymentRequest->payment_transaction_id);
        $this->assertSame(\App\Models\Account\AccountSubscriptionPlan::class, $paymentRequest->payment_transaction);
        $paymentRequest->loadMissing(['paymentTransaction']);
        $this->assertNotNull($paymentRequest->paymentTransaction);

        // Mark as approved so there are no pending requests.
        $paymentRequest->update([
            'status' => AccountPaymentRequestStatusConstant::STATUS_APPROVED,
            'approved_at' => Carbon::now(),
        ]);

        // Run command later; it should process the already-approved request.
        Carbon::setTestNow(Carbon::create(2026, 4, 20, 12, 0, 0));

        $exitCode = Artisan::call('payment-request:approve-or-update', [
            'account_id' => $account->id,
        ]);

        $this->assertSame(0, $exitCode);

        $trialAsp->refresh();

        $this->assertSame(
            Carbon::create(2026, 4, 19, 0, 0, 0)->toDateString(),
            $trialAsp->subscription_starts_at?->toDateString()
        );
    }
}

