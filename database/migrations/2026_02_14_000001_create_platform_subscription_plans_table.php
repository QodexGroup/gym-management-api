<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Trial, Basic, Standard, Premium
            $table->string('slug')->unique(); // trial, basic, standard, premium
            $table->string('interval')->nullable(); // month, quarter, year (null for trial)
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('max_customers')->default(0); // 0 = unlimited
            $table->integer('max_class_schedules')->default(0);
            $table->integer('max_membership_plans')->default(0);
            $table->integer('max_users')->default(0);
            $table->integer('max_pt_packages')->default(0);
            $table->boolean('has_pt')->default(false);
            $table->boolean('has_reports')->default(false);
            $table->integer('trial_days')->nullable(); // 7 for trial plan
            $table->boolean('is_trial')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscription_plans');
    }
};
