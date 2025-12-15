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
        Schema::create('tb_customer_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreignId('customer_id')->constrained('tb_customers');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('discount_percentage', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->date('bill_date');
            $table->enum('bill_status', ['paid', 'partial', 'active'])->default('active');
            $table->string('bill_type');
            $table->unsignedBigInteger('membership_plan_id')->nullable();
            $table->string('custom_service')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('membership_plan_id')->references('id')->on('tb_membership_plan')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_bills');
    }
};
