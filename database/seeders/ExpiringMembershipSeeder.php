<?php

namespace Database\Seeders;

use App\Constant\CustomerMembershipConstant;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExpiringMembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a membership plan
        $membershipPlan = MembershipPlan::where('account_id', 1)->first();
        
        if (!$membershipPlan) {
            $membershipPlan = MembershipPlan::create([
                'account_id' => 1,
                'name' => 'Monthly Membership',
                'description' => 'Standard monthly gym membership',
                'price' => 1500,
                'duration_days' => 30,
                'created_by' => 1,
                'updated_by' => 1,
            ]);
        }

        // Create a customer with expiring membership (expires in 5 days)
        $customer1 = Customer::create([
            'account_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '09123456789',
            'address' => '123 Test Street, Manila',
            'date_of_birth' => '1990-01-15',
            'gender' => 'Male',
            'balance' => 0,
        ]);

        // Deactivate any existing active memberships
        CustomerMembership::where('customer_id', $customer1->id)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);

        // Create membership expiring in 5 days
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $customer1->id,
            'membership_plan_id' => $membershipPlan->id,
            'membership_start_date' => Carbon::now()->subDays(25),
            'membership_end_date' => Carbon::now()->addDays(5), // Expires in 5 days
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create a customer with membership expiring in 3 days
        $customer2 = Customer::create([
            'account_id' => 1,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phone_number' => '09187654321',
            'address' => '456 Sample Avenue, Quezon City',
            'date_of_birth' => '1992-05-20',
            'gender' => 'Female',
            'balance' => 0,
        ]);

        // Deactivate any existing active memberships
        CustomerMembership::where('customer_id', $customer2->id)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);

        // Create membership expiring in 3 days
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $customer2->id,
            'membership_plan_id' => $membershipPlan->id,
            'membership_start_date' => Carbon::now()->subDays(27),
            'membership_end_date' => Carbon::now()->addDays(3), // Expires in 3 days
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create a customer with membership expiring tomorrow
        $customer3 = Customer::create([
            'account_id' => 1,
            'first_name' => 'Mike',
            'last_name' => 'Johnson',
            'email' => 'mike.johnson@example.com',
            'phone_number' => '09191234567',
            'address' => '789 Demo Road, Makati',
            'date_of_birth' => '1988-11-10',
            'gender' => 'Male',
            'balance' => 0,
        ]);

        // Deactivate any existing active memberships
        CustomerMembership::where('customer_id', $customer3->id)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);

        // Create membership expiring tomorrow
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $customer3->id,
            'membership_plan_id' => $membershipPlan->id,
            'membership_start_date' => Carbon::now()->subDays(29),
            'membership_end_date' => Carbon::now()->addDays(1), // Expires tomorrow
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        $this->command->info('✓ Created 3 customers with expiring memberships:');
        $this->command->info('  - John Doe (expires in 5 days)');
        $this->command->info('  - Jane Smith (expires in 3 days)');
        $this->command->info('  - Mike Johnson (expires tomorrow)');
    }
}
