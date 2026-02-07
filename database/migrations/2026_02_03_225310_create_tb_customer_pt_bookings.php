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
        Schema::create('tb_customer_pt_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('pt_package_id');
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('class_schedule_id')->nullable();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->tinyInteger('duration');
            $table->string('booking_notes')->nullable();
            $table->enum('status', ['BOOKED', 'ATTENDED', 'NO_SHOW', 'CANCELLED'])->default('BOOKED');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('tb_customers')->onDelete('cascade');
            $table->foreign('pt_package_id')->references('id')->on('tb_pt_packages')->onDelete('cascade');
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('account_id');
            $table->index('customer_id');
            $table->index('pt_package_id');
            $table->index('coach_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_pt_bookings');
    }
};
