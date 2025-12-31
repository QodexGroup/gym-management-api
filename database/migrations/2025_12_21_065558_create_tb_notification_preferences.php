<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->default(1);
            $table->boolean('membership_expiry_enabled')->default(true);
            $table->boolean('payment_alerts_enabled')->default(true);
            $table->boolean('new_registrations_enabled')->default(true);
            $table->timestamps();
            
            $table->unique('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_notification_preferences');
    }
};
