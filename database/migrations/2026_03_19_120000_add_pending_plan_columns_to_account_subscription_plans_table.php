<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_subscription_plans', function (Blueprint $table) {
            $table->foreignId('pending_subscription_plan_id')
                ->nullable()
                ->after('subscription_plan_id')
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->timestamp('pending_plan_effective_at')
                ->nullable()
                ->after('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('account_subscription_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_subscription_plan_id');
            $table->dropColumn('pending_plan_effective_at');
        });
    }
};

