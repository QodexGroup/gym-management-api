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
        Schema::create('tb_customer_membership', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('membership_plan_id');
            $table->date('membership_start_date');
            $table->date('membership_end_date');
            $table->enum('status', ['active', 'expired', 'deactivated'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('customer_id')
                ->references('id')
                ->on('tb_customers')
                ->onDelete('cascade');

            $table->foreign('membership_plan_id')
                ->references('id')
                ->on('tb_membership_plan')
                ->onDelete('restrict');

            // Indexes for better query performance
            $table->index('customer_id');
            $table->index('membership_plan_id');
            $table->index('status');
            $table->index('membership_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_membership');
    }
};
