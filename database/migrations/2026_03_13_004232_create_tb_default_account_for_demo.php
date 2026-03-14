<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make sure accounts table exists
        if (! Schema::hasTable('accounts')) {
            return;
        }

        // Create or update the default demo account with id = 1
        DB::table('accounts')->updateOrInsert(
            ['id' => 1],
            [
                'account_name'    => 'Demo Gym',
                'account_email'   => 'admin@gym.com',
                'account_phone'   => '09123456789',
                'status'          => 'active',
                'billing_name'    => 'Demo Gym',
                'billing_email'   => 'admin@gym.com',
                'billing_phone'   => '09123456789',
                'billing_address' => '123 Main St, Anytown, USA',
                'billing_city'    => 'Anytown',
                'billing_province'=> 'CA',
                'billing_zip'     => '12345',
                'billing_country' => 'USA',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        // Optionally attach a trial subscription plan to the demo account
        // This keeps the seed consistent with normal signup flow.
        if (Schema::hasTable('subscription_plans') && Schema::hasTable('account_subscription_plans')) {
            $trialPlan = DB::table('subscription_plans')
                ->where('slug', 'trial-subscription')
                ->first();

            if ($trialPlan) {
                $now = now();
                $trialEndsAt = (clone $now)->addDays($trialPlan->trial_days ?? 7);

                DB::table('account_subscription_plans')->updateOrInsert(
                    [
                        'account_id' => 1,
                        'subscription_plan_id' => $trialPlan->id,
                    ],
                    [
                        'plan_name' => $trialPlan->name,
                        'trial_starts_at' => $now,
                        'trial_ends_at' => $trialEndsAt,
                        'subscription_starts_at' => null,
                        'subscription_ends_at' => null,
                        'locked_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        // Optionally remove the demo account on rollback
        DB::table('accounts')->where('id', 1)->delete();
    }
};
