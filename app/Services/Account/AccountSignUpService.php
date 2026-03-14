<?php

namespace App\Services\Account;

use App\Constants\DefaultSignupCategories;
use App\Models\Account\Account;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\PtCategory;
use App\Models\Common\ExpenseCategory;
use App\Models\User;
use App\Repositories\Account\AccountRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountSignUpService
{
    public function __construct(
        private AccountRepository $accountRepository
    ) {
    }

    /**
     * Create account and first user (owner).
     *
     * @return array{user: User, account: Account}
     */
    public function signUp(string $firebaseUid, array $data): array
    {
        // Check if user already exists
        $existingUser = $this->accountRepository->findUserByFirebaseUid($firebaseUid);
        if ($existingUser) {
            throw new \Exception('User already exists. Please log in.');
        }

        // One-trial-per-email: block if email already used for a trial account
        $email = $data['email'] ?? null;
        if ($email && $this->accountRepository->accountExistsByEmail($email)) {
            throw new \Exception('An account with this email has already started a trial. Please log in or choose a different email.');
        }

        // Find trial plan
        $trialPlan = $this->accountRepository->findTrialPlan();
        if (!$trialPlan) {
            throw new \Exception('Trial subscription plan not found. Please contact support.');
        }

        $trialEndsAt = now()->addDays($trialPlan->trial_days ?? 7);

        return DB::transaction(function () use ($firebaseUid, $data, $trialPlan, $trialEndsAt) {
            // Create account
            $account = $this->accountRepository->createAccountFromSignup($data);

            // Create account subscription plan
            $this->accountRepository->createTrialAccountSubscriptionPlan($account->id, $trialPlan, $trialEndsAt);

            // Create user
            $user = $this->accountRepository->createUserFromSignup($firebaseUid, $account->id, $data);

            // Seed default categories
            $this->seedDefaultCategoriesForAccount($account->id);

            // Load relationships
            $user->load(['account.activeAccountSubscriptionPlan.subscriptionPlan', 'permissions']);

            return [
                'user' => $user,
                'account' => $account,
            ];
        });
    }

    /**
     * Default expense category names seeded for new accounts. Exposed for testing without DB.
     *
     * @return list<string>
     */
    public static function defaultExpenseCategoryNames(): array
    {
        return DefaultSignupCategories::DEFAULT_EXPENSE_CATEGORIES;
    }

    /**
     * Default PT category names seeded for new accounts. Exposed for testing without DB.
     *
     * @return list<string>
     */
    public static function defaultPtCategoryNames(): array
    {
        return DefaultSignupCategories::DEFAULT_PT_CATEGORIES;
    }

    private function seedDefaultCategoriesForAccount(int $accountId): void
    {
        try {
            foreach (DefaultSignupCategories::DEFAULT_EXPENSE_CATEGORIES as $name) {
                ExpenseCategory::firstOrCreate(
                    ['account_id' => $accountId, 'name' => $name],
                    ['account_id' => $accountId, 'name' => $name]
                );
            }
            foreach (DefaultSignupCategories::DEFAULT_PT_CATEGORIES as $categoryName) {
                PtCategory::firstOrCreate(
                    ['account_id' => $accountId, 'category_name' => $categoryName],
                    ['account_id' => $accountId, 'category_name' => $categoryName]
                );
            }
        } catch (\Exception $e) {
            Log::error('Error seeding default categories', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - categories are not critical for signup
        }
    }
}
