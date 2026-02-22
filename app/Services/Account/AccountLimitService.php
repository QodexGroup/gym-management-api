<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Account\ClassSchedule;
use App\Models\Account\MembershipPlan;
use App\Models\Account\PlatformSubscriptionPlan;
use App\Models\Account\PtPackage;
use App\Models\Core\Customer;
use App\Models\User;

class AccountLimitService
{
    public const RESOURCE_CUSTOMERS = 'customers';
    public const RESOURCE_CLASS_SCHEDULES = 'class_schedules';
    public const RESOURCE_MEMBERSHIP_PLANS = 'membership_plans';
    public const RESOURCE_USERS = 'users';
    public const RESOURCE_PT_PACKAGES = 'pt_packages';

    /**
     * Check if account can create the given resource.
     *
     * @return array{allowed: bool, message: string|null, current: int, limit: int}
     */
    public function canCreate(int $accountId, string $resource): array
    {
        $account = Account::with('subscriptionPlan')->find($accountId);

        if (!$account) {
            return ['allowed' => false, 'message' => 'Account not found', 'current' => 0, 'limit' => 0];
        }

        // Block if trial expired or cancelled
        if (!$account->canCreatePaidResources()) {
            return [
                'allowed' => false,
                'message' => 'Your free trial has expired. Please choose a subscription plan to continue.',
                'current' => 0,
                'limit' => 0,
            ];
        }

        // Auto-expire trial if past trial_ends_at
        if ($account->isTrialExpired()) {
            $account->update(['subscription_status' => Account::STATUS_TRIAL_EXPIRED]);
            return [
                'allowed' => false,
                'message' => 'Your free trial has expired. Please choose a subscription plan to continue.',
                'current' => 0,
                'limit' => 0,
            ];
        }

        $plan = $account->getEffectivePlan();
        if (!$plan) {
            return ['allowed' => false, 'message' => 'No plan assigned', 'current' => 0, 'limit' => 0];
        }

        $limit = $plan->getLimit($resource);
        if ($plan->isUnlimited($resource)) {
            return ['allowed' => true, 'message' => null, 'current' => $this->getCount($accountId, $resource), 'limit' => 0];
        }

        $current = $this->getCount($accountId, $resource);
        $allowed = $current < $limit;

        $message = null;
        if (!$allowed) {
            $resourceLabel = $this->getResourceLabel($resource);
            $message = "Free trial limit reached ({$limit} {$resourceLabel}). Upgrade to add more.";
        }

        return [
            'allowed' => $allowed,
            'message' => $message,
            'current' => $current,
            'limit' => $limit,
        ];
    }

    public function getCount(int $accountId, string $resource): int
    {
        switch ($resource) {
            case self::RESOURCE_CUSTOMERS:
                return Customer::where('account_id', $accountId)->count();
            case self::RESOURCE_CLASS_SCHEDULES:
                return ClassSchedule::where('account_id', $accountId)->count();
            case self::RESOURCE_MEMBERSHIP_PLANS:
                return MembershipPlan::where('account_id', $accountId)->count();
            case self::RESOURCE_USERS:
                return User::where('account_id', $accountId)->count();
            case self::RESOURCE_PT_PACKAGES:
                return PtPackage::where('account_id', $accountId)->count();
            default:
                return 0;
        }
    }

    private function getResourceLabel(string $resource): string
    {
        switch ($resource) {
            case self::RESOURCE_CUSTOMERS:
                return 'customers';
            case self::RESOURCE_CLASS_SCHEDULES:
                return 'class schedules';
            case self::RESOURCE_MEMBERSHIP_PLANS:
                return 'membership plans';
            case self::RESOURCE_USERS:
                return 'users';
            case self::RESOURCE_PT_PACKAGES:
                return 'PT packages';
            default:
                return $resource;
        }
    }

    /**
     * Get usage summary for account (for dashboard).
     *
     * @return array<string, array{current: int, limit: int}>
     */
    public function getUsageSummary(int $accountId): array
    {
        $resources = [
            self::RESOURCE_CUSTOMERS,
            self::RESOURCE_CLASS_SCHEDULES,
            self::RESOURCE_MEMBERSHIP_PLANS,
            self::RESOURCE_USERS,
            self::RESOURCE_PT_PACKAGES,
        ];

        $account = Account::with('subscriptionPlan')->find($accountId);
        $plan = $account ? $account->getEffectivePlan() : null;

        $result = [];
        foreach ($resources as $resource) {
            $current = $this->getCount($accountId, $resource);
            $limit = ($plan !== null) ? $plan->getLimit($resource) : 0;
            $result[$resource] = ['current' => $current, 'limit' => $limit];
        }

        return $result;
    }
}
