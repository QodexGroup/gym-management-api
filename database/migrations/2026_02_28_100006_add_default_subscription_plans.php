<?php

use App\Models\Account\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
       SubscriptionPlan::create([
        'name' => 'Trial Subscription',
        'slug' => 'trial-subscription',
        'interval' => 'month',
        'price' => 0,
        'max_customers' => 0,
        'max_class_schedules' => 0,
        'max_membership_plans' => 0,
        'max_users' => 0,
        'max_pt_packages' => 0,
        'has_pt' => false,
        'has_reports' => false,
        'trial_days' => 7,
        'is_trial' => true,
       ]);

       SubscriptionPlan::create([
        'name' => 'Monthly Subscription',
        'slug' => 'monthly-subscription',
        'interval' => 'month',
        'price' => 1200,
        'max_customers' => 0,
        'max_class_schedules' => 0,
        'max_membership_plans' => 0,
        'max_users' => 0,
        'max_pt_packages' => 0,
        'has_pt' => false,
        'has_reports' => false,
        'trial_days' => 7,
        'is_trial' => false,
       ]);

       SubscriptionPlan::create([
        'name' => 'Quarterly Subscription',
        'slug' => 'quarterly-subscription',
        'interval' => 'quarter',
        'price' => 3400,
        'max_customers' => 0,
        'max_class_schedules' => 0,
        'max_membership_plans' => 0,
        'max_users' => 0,
        'max_pt_packages' => 0,
        'has_pt' => false,
        'has_reports' => false,
        'trial_days' => 7,
        'is_trial' => false,
       ]);

       SubscriptionPlan::create([
        'name' => 'Yearly Subscription',
        'slug' => 'yearly-subscription',
        'interval' => 'year',
        'price' => 13000,
        'max_customers' => 0,
        'max_class_schedules' => 0,
        'max_membership_plans' => 0,
        'max_users' => 0,
        'max_pt_packages' => 0,
        'has_pt' => false,
        'has_reports' => false,
        'trial_days' => 7,
        'is_trial' => false,
       ]);
    }

    public function down(): void
    {

    }
};
