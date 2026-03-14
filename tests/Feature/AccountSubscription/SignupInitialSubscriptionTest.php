<?php

namespace Tests\Feature\AccountSubscription;

use App\Models\Account\AccountSubscriptionPlan;
use App\Services\Account\AccountSignUpService;
use Carbon\Carbon;

class SignupInitialSubscriptionTest extends AccountSubscriptionFlowTestCase
{
    public function test_signup_creates_account_user_and_trial_plan_without_invoices(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 9, 0, 0));

        $service = app(AccountSignUpService::class);
        $data = [
            'accountName' => 'New Gym',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '099999999',
            'billingName' => 'Jane Doe',
            'billingEmail' => 'billing@example.com',
            'billingPhone' => '099999999',
            'billingAddress' => '123 Test St',
            'billingCity' => 'Metro',
            'billingProvince' => 'Metro',
            'billingZip' => '1000',
            'billingCountry' => 'PH',
        ];

        $result = $service->signUp('firebase-uid-123', $data);

        $this->assertDatabaseHas('accounts', [
            'id' => $result['account']->id,
            'account_email' => $data['email'],
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $result['user']->id,
            'account_id' => $result['account']->id,
            'firebase_uid' => 'firebase-uid-123',
        ]);

        $plan = AccountSubscriptionPlan::where('account_id', $result['account']->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame($this->trialPlan->id, $plan->subscription_plan_id);
        $this->assertNotNull($plan->trial_starts_at);
        $this->assertEquals(Carbon::now()->toDateString(), $plan->trial_starts_at->toDateString());
        $this->assertEquals(Carbon::now()->addDays($this->trialPlan->trial_days)->toDateString(), $plan->trial_ends_at->toDateString());
        $this->assertNull($plan->subscription_starts_at);
        $this->assertNull($plan->subscription_ends_at);

        $this->assertDatabaseCount('account_invoices', 0);
    }
}
