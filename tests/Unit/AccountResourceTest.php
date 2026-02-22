<?php

namespace Tests\Unit;

/**
 * Tests for AccountResource transformation.
 * Uses fake in-memory models only (no database).
 */
use App\Http\Resources\Account\AccountResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\Unit\UnitTestCase;

class AccountResourceTest extends UnitTestCase
{
    public function test_account_resource_returns_correct_structure_with_null_dates(): void
    {
        $account = $this->createFakeAccount(1, 'Fake Gym Co', 'active', null, null);
        // Do not setRelation – subscriptionPlan not loaded, so it won't appear in output

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Fake Gym Co', $data['name']);
        $this->assertEquals('active', $data['subscriptionStatus']);
        $this->assertNull($data['trialEndsAt']);
        $this->assertNull($data['currentPeriodEndsAt']);
        $this->assertArrayNotHasKey('subscriptionPlan', $data);
    }

    public function test_account_resource_formats_dates_as_iso8601(): void
    {
        $trialEnds = Carbon::parse('2026-03-01 10:00:00');
        $periodEnds = Carbon::parse('2026-02-28 23:59:59');

        $account = $this->createFakeAccount(2, 'Test Fitness', 'trial', $trialEnds, $periodEnds);

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertEquals($trialEnds->toIso8601String(), $data['trialEndsAt']);
        $this->assertEquals($periodEnds->toIso8601String(), $data['currentPeriodEndsAt']);
    }

    public function test_account_resource_includes_subscription_plan_when_loaded(): void
    {
        $plan = $this->createFakePlan(5, 'Premium', 'premium', false);

        $account = $this->createFakeAccount(3, 'Elite Gym', 'active', null, null);
        $account->setRelation('subscriptionPlan', $plan);

        $resource = new AccountResource($account);
        $data = $resource->resolve(new Request());

        $this->assertArrayHasKey('subscriptionPlan', $data);
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
        $this->assertEquals($trialEnds->toIso8601String(), $data['trialEndsAt']);
    }

    private function createFakeAccount(
        int $id,
        string $name,
        string $subscriptionStatus,
        ?Carbon $trialEndsAt,
        ?Carbon $currentPeriodEndsAt
    ): \App\Models\Account {
        $account = new \App\Models\Account();
        $account->id = $id;
        $account->name = $name;
        $account->subscription_status = $subscriptionStatus;
        $account->trial_ends_at = $trialEndsAt;
        $account->current_period_ends_at = $currentPeriodEndsAt;
        $account->exists = true;
        return $account;
    }

    private function createFakePlan(int $id, string $name, string $slug, bool $isTrial): \App\Models\Account\PlatformSubscriptionPlan
    {
        $plan = new \App\Models\Account\PlatformSubscriptionPlan();
        $plan->id = $id;
        $plan->name = $name;
        $plan->slug = $slug;
        $plan->is_trial = $isTrial;
        return $plan;
    }
}
