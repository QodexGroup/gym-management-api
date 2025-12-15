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
        Schema::create('tb_customer_payment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreignId('customer_id')->constrained('tb_customers');
            $table->foreignId('customer_bill_id')->constrained('tb_customer_bills');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method');
            $table->date('payment_date');
            $table->string('reference_number')->nullable();
            $table->string('remarks')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_payment');
    }
};
