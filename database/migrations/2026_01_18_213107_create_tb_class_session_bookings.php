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
        Schema::create('tb_class_session_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('class_schedule_session_id');
            $table->unsignedBigInteger('customer_id');
            $table->enum('status', ['BOOKED', 'ATTENDED', 'NO_SHOW', 'CANCELLED'])->default('BOOKED');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('class_schedule_session_id')
                  ->references('id')
                  ->on('tb_class_schedule_sessions')
                  ->onDelete('cascade');
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('tb_customers')
                  ->onDelete('cascade');

            // Prevent duplicate bookings
            $table->unique(['class_schedule_session_id', 'customer_id'], 'unique_booking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_class_session_bookings');
    }
};
