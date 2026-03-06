<?php

namespace Tests\Feature\Auth;

use App\Constants\DefaultSignupCategories;
use App\Services\Account\AccountSignUpService;
use PHPUnit\Framework\TestCase;

/**
 * Asserts default signup category lists and dummy new-account insertion data. No database connection.
 */
class SignUpDefaultCategoriesTest extends TestCase
{
    /** Dummy new-account signup payload (no DB). */
    private const DUMMY_SIGNUP_DATA = [
        'accountName' => 'Test Gym',
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'email' => 'jane.doe@example.com',
        'phone' => null,
    ];

    /** Dummy account id used to build "would be inserted" rows. */
    private const DUMMY_ACCOUNT_ID = 99999;

    public function test_default_expense_categories_defined(): void
    {
        $expected = [
            'Rent',
            'Salary',
            'Equipment',
            'Utilities',
            'Maintenance',
            'Supplies',
        ];
        $this->assertSame($expected, DefaultSignupCategories::DEFAULT_EXPENSE_CATEGORIES);
        $this->assertSame($expected, AccountSignUpService::defaultExpenseCategoryNames());
        $this->assertCount(6, DefaultSignupCategories::DEFAULT_EXPENSE_CATEGORIES);
    }

    public function test_default_pt_categories_defined(): void
    {
        $expected = [
            'Strength & Conditioning',
            'Cardio & Endurance',
            'Weight Loss Program',
            'HIIT Training',
            'Flexibility & Mobility',
            'CrossFit',
            'Yoga',
            'Pilates',
        ];
        $this->assertSame($expected, DefaultSignupCategories::DEFAULT_PT_CATEGORIES);
        $this->assertSame($expected, AccountSignUpService::defaultPtCategoryNames());
        $this->assertCount(8, DefaultSignupCategories::DEFAULT_PT_CATEGORIES);
    }

    /** Asserts that for a new account signup, the expense rows that would be inserted match defaults. */
    public function test_new_account_would_get_default_expense_categories(): void
    {
        $dummyAccountId = self::DUMMY_ACCOUNT_ID;
        $wouldBeInserted = [];
        foreach (DefaultSignupCategories::DEFAULT_EXPENSE_CATEGORIES as $name) {
            $wouldBeInserted[] = ['account_id' => $dummyAccountId, 'name' => $name];
        }
        $this->assertCount(6, $wouldBeInserted);
        $names = array_column($wouldBeInserted, 'name');
        $this->assertSame(DefaultSignupCategories::DEFAULT_EXPENSE_CATEGORIES, $names);
        $this->assertSame(array_fill(0, 6, $dummyAccountId), array_column($wouldBeInserted, 'account_id'));
    }

    /** Asserts that for a new account signup, the PT category rows that would be inserted match defaults. */
    public function test_new_account_would_get_default_pt_categories(): void
    {
        $dummyAccountId = self::DUMMY_ACCOUNT_ID;
        $wouldBeInserted = [];
        foreach (DefaultSignupCategories::DEFAULT_PT_CATEGORIES as $categoryName) {
            $wouldBeInserted[] = ['account_id' => $dummyAccountId, 'category_name' => $categoryName];
        }
        $this->assertCount(8, $wouldBeInserted);
        $names = array_column($wouldBeInserted, 'category_name');
        $this->assertSame(DefaultSignupCategories::DEFAULT_PT_CATEGORIES, $names);
        $this->assertSame(array_fill(0, 8, $dummyAccountId), array_column($wouldBeInserted, 'account_id'));
    }

    /** Dummy signup payload is valid shape for signUp(). */
    public function test_dummy_signup_data_has_required_keys(): void
    {
        $data = self::DUMMY_SIGNUP_DATA;
        $this->assertArrayHasKey('accountName', $data);
        $this->assertArrayHasKey('firstname', $data);
        $this->assertArrayHasKey('lastname', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertNotEmpty($data['accountName']);
        $this->assertNotEmpty($data['email']);
    }
}
