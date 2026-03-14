<?php

namespace App\Repositories\Account;

use App\Constant\AccountStatusConstant;
use App\Models\Account\Account;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;

class AccountRepository
{
    /**
     * Find user by Firebase UID.
     *
     * @param string $firebaseUid
     *
     * @return User|null
     */
    public function findUserByFirebaseUid(string $firebaseUid): ?User
    {
        return User::where('firebase_uid', $firebaseUid)->first();
    }

    /**
     * Check if account exists with email.
     *
     * @param string $email
     *
     * @return bool
     */
    public function accountExistsByEmail(string $email): bool
    {
        return Account::where('account_email', $email)->exists();
    }

    /**
     * Find trial subscription plan.
     *
     * @return SubscriptionPlan|null
     */
    public function findTrialPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', 'trial-subscription')->first();
    }

    /**
     * Create account from signup data.
     *
     * @param array $signupData
     *
     * @return Account
     */
    public function createAccountFromSignup(array $signupData): Account
    {
        return Account::create([
            'accountName' => $signupData['accountName'],
            'accountEmail' => $signupData['email'] ?? null,
            'accountPhone' => $signupData['phone'] ?? null,
            'status' => AccountStatusConstant::STATUS_ACTIVE,
            'billingName' => $signupData['billingName'],
            'billingEmail' => $signupData['billingEmail'],
            'billingPhone' => $signupData['billingPhone'],
            'billingAddress' => $signupData['billingAddress'],
            'billingCity' => $signupData['billingCity'],
            'billingProvince' => $signupData['billingProvince'],
            'billingZip' => $signupData['billingZip'],
            'billingCountry' => $signupData['billingCountry'],
        ]);
    }


    /**
     * Create account subscription plan for signup (trial).
     */
    public function createTrialAccountSubscriptionPlan(int $accountId, SubscriptionPlan $trialPlan, Carbon $trialEndsAt): AccountSubscriptionPlan
    {
        return AccountSubscriptionPlan::create([
            'account_id' => $accountId,
            'subscription_plan_id' => $trialPlan->id,
            'plan_name' => $trialPlan->name,
            'trial_starts_at' => now(),
            'trial_ends_at' => $trialEndsAt,
            'subscription_starts_at' => null,
            'subscription_ends_at' => null,
        ]);
    }



    /**
     * Create user from signup data.
     *
     * @param string $firebaseUid
     * @param int $accountId
     * @param array $signupData
     *
     * @return User
     */
    public function createUserFromSignup(string $firebaseUid, int $accountId, array $signupData): User
    {
        return User::create([
            'account_id' => $accountId,
            'firebase_uid' => $firebaseUid,
            'firstname' => $signupData['firstname'],
            'lastname' => $signupData['lastname'],
            'email' => $signupData['email'] ?? null,
            'phone' => $signupData['phone'] ?? null,
            'role' => 'admin',
            'is_account_owner' => true,
            'status' => 'active',
        ]);

    }

    /**
     * Find account by ID with relationships.
     *
     * @param int $accountId
     *
     * @return Account|null
     */
    public function findAccountWithRelations(int $accountId): ?Account
    {
        return Account::with('activeAccountSubscriptionPlan.subscriptionPlan')->find($accountId);
    }

    /**
     * Update account.
     *
     * @param int $accountId
     * @param array $data
     *
     * @return Account
     */
    public function updateAccount(int $accountId, array $data): Account
    {
        $account = Account::findOrFail($accountId);
        $account->update($data);
        return $account->fresh();
    }

    /**
     * @param array<int> $accountIds
     *
     * @return int
     */
    public function deactivateActiveAccountsByIds(array $accountIds): int
    {
        return Account::whereIn('id', $accountIds)
            ->where('status', AccountStatusConstant::STATUS_ACTIVE)
            ->update(['status' => AccountStatusConstant::STATUS_DEACTIVATED]);
    }

    /**
     * @param int $accountId
     *
     * @return int
     */
    public function activateAccountById(int $accountId): int
    {
        return Account::where('id', $accountId)
            ->update(['status' => AccountStatusConstant::STATUS_ACTIVE]);
    }
}
