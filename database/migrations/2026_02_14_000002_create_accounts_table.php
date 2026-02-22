<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Gym/organization name
            $table->enum('subscription_status', [
                'trial', 'active', 'trial_expired', 'past_due', 'cancelled'
            ])->default('trial');
            $table->foreignId('subscription_plan_id')->nullable()
                ->constrained('platform_subscription_plans')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('owner_email')->nullable(); // For one-trial-per-email check
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
