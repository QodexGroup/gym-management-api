<?php

namespace Tests\Feature\Account;

use App\Constant\AccountStatusConstant;
use App\Models\Account\Account;
use App\Models\User;
use App\Rules\ValidEmail;
use App\Services\Account\UsersService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class UserEmailGlobalUniquenessTest extends TestCase
{
    private const SHARED_EMAIL = 'shared-user@example.com';

    protected function createAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'account_name' => 'Test Gym ' . uniqid(),
            'account_email' => 'owner' . uniqid() . '@example.com',
            'account_phone' => '1234567890',
            'status' => AccountStatusConstant::STATUS_ACTIVE,
        ], $overrides));
    }

    protected function createUserForAccount(Account $account, array $overrides = []): User
    {
        return User::create(array_merge([
            'account_id' => $account->id,
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'user' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => AccountStatusConstant::STATUS_ACTIVE,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function userEmailRules(?int $ignoreUserId = null): array
    {
        return [
            'email' => [
                'required',
                new ValidEmail(),
                'max:255',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreUserId),
            ],
        ];
    }

    public function test_cross_account_create_with_same_email_is_rejected(): void
    {
        $accountOne = $this->createAccount();
        $accountTwo = $this->createAccount();

        $this->createUserForAccount($accountOne, ['email' => self::SHARED_EMAIL]);

        $validator = Validator::make(
            ['email' => self::SHARED_EMAIL],
            $this->userEmailRules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_same_user_update_with_unchanged_email_is_allowed(): void
    {
        $account = $this->createAccount();
        $user = $this->createUserForAccount($account, ['email' => self::SHARED_EMAIL]);

        $validator = Validator::make(
            ['email' => self::SHARED_EMAIL],
            $this->userEmailRules($user->id)
        );

        $this->assertFalse($validator->fails());
    }

    public function test_email_is_reusable_after_soft_delete(): void
    {
        $account = $this->createAccount();
        $user = $this->createUserForAccount($account, [
            'email' => self::SHARED_EMAIL,
            'firebase_uid' => null,
        ]);

        $deleted = app(UsersService::class)->deleteUser($user->id, $account->id);

        $this->assertTrue($deleted);

        $trashedUser = User::withTrashed()->find($user->id);
        $this->assertNotNull($trashedUser);
        $this->assertNull($trashedUser->email);
        $this->assertNull($trashedUser->firebase_uid);

        $validator = Validator::make(
            ['email' => self::SHARED_EMAIL],
            $this->userEmailRules()
        );

        $this->assertFalse($validator->fails());

        $replacementUser = $this->createUserForAccount($account, ['email' => self::SHARED_EMAIL]);
        $this->assertSame(self::SHARED_EMAIL, $replacementUser->email);
    }
}
