<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_subscription_plans')->where('slug', 'basic')->update([
            'max_class_schedules' => 0,
            'max_users' => 10,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'standard')->update([
            'max_class_schedules' => 0,
            'max_users' => 20,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'premium')->update([
            'max_customers' => 0,
            'max_class_schedules' => 0,
            'max_membership_plans' => 0,
            'max_users' => 0,
            'max_pt_packages' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('platform_subscription_plans')->where('slug', 'basic')->update([
            'max_class_schedules' => 5,
            'max_users' => 5,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'standard')->update([
            'max_class_schedules' => 20,
            'max_users' => 15,
            'updated_at' => now(),
        ]);
    }
};
