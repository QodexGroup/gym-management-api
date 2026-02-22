<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Insert Trial plan (used for new sign-ups)
        DB::table('platform_subscription_plans')->insert([
            'name' => 'Free Trial',
            'slug' => 'trial',
            'interval' => null,
            'price' => 0,
            'max_customers' => 10,
            'max_class_schedules' => 2,
            'max_membership_plans' => 2,
            'max_users' => 2,
            'max_pt_packages' => 2,
            'has_pt' => true,
            'has_reports' => true,
            'trial_days' => 7,
            'is_trial' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert Basic (monthly), Standard (quarterly), Premium (yearly)
        DB::table('platform_subscription_plans')->insert([
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'interval' => 'month',
                'price' => 10,
                'max_customers' => 100,
                'max_class_schedules' => 5,
                'max_membership_plans' => 10,
                'max_users' => 5,
                'max_pt_packages' => 10,
                'has_pt' => true,
                'has_reports' => false,
                'trial_days' => null,
                'is_trial' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'interval' => 'quarter',
                'price' => 25,
                'max_customers' => 500,
                'max_class_schedules' => 20,
                'max_membership_plans' => 25,
                'max_users' => 15,
                'max_pt_packages' => 25,
                'has_pt' => true,
                'has_reports' => true,
                'trial_days' => null,
                'is_trial' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'interval' => 'year',
                'price' => 90,
                'max_customers' => 0, // unlimited
                'max_class_schedules' => 0,
                'max_membership_plans' => 0,
                'max_users' => 0,
                'max_pt_packages' => 0,
                'has_pt' => true,
                'has_reports' => true,
                'trial_days' => null,
                'is_trial' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Default account (id=1) for existing installations (users with account_id=1)
        $trialPlanId = DB::table('platform_subscription_plans')->where('slug', 'trial')->value('id');
        DB::table('accounts')->insert([
            'id' => 1,
            'name' => 'Default Account',
            'subscription_status' => 'active', // Legacy: treat as active so existing data works
            'subscription_plan_id' => $trialPlanId,
            'trial_ends_at' => null,
            'current_period_ends_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->where('id', 1)->delete();
        DB::table('platform_subscription_plans')->truncate();
    }
};
