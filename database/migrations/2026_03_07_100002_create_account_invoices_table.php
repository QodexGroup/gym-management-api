<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('account_subscription_plan_id')->nullable()->constrained('account_subscription_plans')->nullOnDelete();
            $table->string('invoice_number', 32)->unique();
            $table->string('billing_period', 16)->nullable(); // mdY e.g. 03072026
            $table->date('invoice_date')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'overdue', 'void'])->default('pending');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->tinyInteger('prorate')->default(0);
            $table->text('invoice_details')->nullable();  //list of items and their prices , breakdown of the total amount
            $table->timestamps();

            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invoices');
    }
};
