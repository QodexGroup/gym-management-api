<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Account\PlatformSubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountSignUpService
{
    /**
     * Create account and first user (owner).
     * Idempotent: if user exists with same firebase_uid, returns existing user.
     *
     * @return array{user: User, account: Account, isNew: bool}
     */
    public function signUp(string $firebaseUid, array $data): array
    {
        // Idempotent: user already exists
        $existingUser = User::where('firebase_uid', $firebaseUid)->first();
        if ($existingUser) {
            $existingUser->load(['account.subscriptionPlan', 'permissions']);
            return [
                'user' => $existingUser,
                'account' => $existingUser->account,
                'isNew' => false,
            ];
        }

        // One-trial-per-email: block if email already used for a trial account
        $email = $data['email'] ?? null;
        if ($email && Account::where('owner_email', $email)->exists()) {
            throw new \Exception('An account with this email has already started a trial. Please log in or choose a different email.');
        }

        $trialPlan = PlatformSubscriptionPlan::where('slug', 'trial')->firstOrFail();
        $trialEndsAt = now()->addDays($trialPlan->trial_days ?? 7);

        return DB::transaction(function () use ($firebaseUid, $data, $trialPlan, $trialEndsAt) {
            $account = Account::create([
                'name' => $data['accountName'],
                'subscription_status' => Account::STATUS_TRIAL,
                'subscription_plan_id' => $trialPlan->id,
                'trial_ends_at' => $trialEndsAt,
                'owner_email' => $data['email'] ?? null,
            ]);

            $user = User::create([
                'account_id' => $account->id,
                'firebase_uid' => $firebaseUid,
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'role' => 'admin',
                'status' => 'active',
            ]);

            $user->load(['account.subscriptionPlan', 'permissions']);

            return [
                'user' => $user,
                'account' => $account,
                'isNew' => true,
            ];
        });
    }
}
