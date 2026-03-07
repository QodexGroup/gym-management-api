<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_subscription_plans')->where('slug', 'basic')->update([
            'price' => 980,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'standard')->update([
            'price' => 2900,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'premium')->update([
            'price' => 11500,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('platform_subscription_plans')->where('slug', 'basic')->update([
            'price' => 10,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'standard')->update([
            'price' => 25,
            'updated_at' => now(),
        ]);

        DB::table('platform_subscription_plans')->where('slug', 'premium')->update([
            'price' => 90,
            'updated_at' => now(),
        ]);
    }
};
