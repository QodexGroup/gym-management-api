<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('plan_name')->nullable();
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_subscription_plans');
    }
};
