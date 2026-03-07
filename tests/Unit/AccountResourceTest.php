<?php

namespace Tests\Unit;

use App\Http\Resources\Account\AccountResource;
use App\Models\Account;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\PlatformSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\Support\UnitTestCase;

class AccountResourceTest extends UnitTestCase
{
    public function test_account_resource_returns_correct_structure_with_null_dates(): void
    {
        $account = $this->createFakeAccount(1, 'Fake Gym Co', 'active', null, null);
        $account->setRelation('activeAccountSubscriptionPlan', null);

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Fake Gym Co', $data['name']);
        $this->assertEquals('active', $data['subscriptionStatus']);
        $this->assertNull($data['trialEndsAt']);
        $this->assertNull($data['currentPeriodEndsAt']);
        $this->assertNull($data['subscriptionPlan']);
        $this->assertArrayHasKey('billingInformation', $data);
    }

    public function test_account_resource_returns_dates_without_explicit_formatting(): void
    {
        $trialEnds = Carbon::parse('2026-03-01 10:00:00');
        $periodEnds = Carbon::parse('2026-02-28 23:59:59');

        $account = $this->createFakeAccount(2, 'Test Fitness', 'trial', $trialEnds, $periodEnds);

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertEquals($trialEnds->toDateTimeString(), Carbon::parse($data['trialEndsAt'])->toDateTimeString());
        $this->assertEquals($periodEnds->toDateTimeString(), Carbon::parse($data['currentPeriodEndsAt'])->toDateTimeString());
    }

    public function test_account_resource_includes_subscription_plan_when_loaded(): void
    {
        $plan = $this->createFakePlan(5, 'Premium', 'premium', false);
        $asp = $this->createFakeAccountSubscriptionPlan(10, null, null);
        $asp->setRelation('platformPlan', $plan);

        $account = $this->createFakeAccount(3, 'Elite Gym', 'active', null, null);
        $account->setRelation('activeAccountSubscriptionPlan', $asp);

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertNotNull($data['subscriptionPlan']);
        $this->assertEquals(5, $data['subscriptionPlan']['id']);
        $this->assertEquals('Premium', $data['subscriptionPlan']['name']);
        $this->assertEquals('premium', $data['subscriptionPlan']['slug']);
        $this->assertFalse($data['subscriptionPlan']['isTrial']);
    }

    public function test_account_resource_with_trial_expired_status(): void
    {
        $trialEnds = Carbon::yesterday();
        $account = $this->createFakeAccount(4, 'Expired Trial Gym', 'trial_expired', $trialEnds, null);

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertEquals('trial_expired', $data['subscriptionStatus']);
        $this->assertEquals($trialEnds->toDateTimeString(), Carbon::parse($data['trialEndsAt'])->toDateTimeString());
    }

    private function createFakeAccount(
        int $id,
        string $name,
        string $subscriptionStatus,
        ?Carbon $trialEndsAt,
        ?Carbon $currentPeriodEndsAt
    ): Account {
        $account = new Account();
        $account->id = $id;
        $account->name = $name;
        $account->subscription_status = $subscriptionStatus;
        $account->exists = true;
        if ($trialEndsAt !== null || $currentPeriodEndsAt !== null) {
            $asp = new AccountSubscriptionPlan();
            $asp->trial_ends_at = $trialEndsAt;
            $asp->subscription_ends_at = $currentPeriodEndsAt;
            $account->setRelation('activeAccountSubscriptionPlan', $asp);
        }
        return $account;
    }

    private function createFakeAccountSubscriptionPlan(int $id, ?Carbon $trialEndsAt, ?Carbon $subscriptionEndsAt): AccountSubscriptionPlan
    {
        $asp = new AccountSubscriptionPlan();
        $asp->id = $id;
        $asp->trial_ends_at = $trialEndsAt;
        $asp->subscription_ends_at = $subscriptionEndsAt;
        $asp->exists = true;
        return $asp;
    }

    private function createFakePlan(int $id, string $name, string $slug, bool $isTrial): PlatformSubscriptionPlan
    {
        $plan = new PlatformSubscriptionPlan();
        $plan->id = $id;
        $plan->name = $name;
        $plan->slug = $slug;
        $plan->is_trial = $isTrial;
        return $plan;
    }
}
