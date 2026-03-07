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
            $table->string('plan_name')->nullable();
            $table->string('plan_interval', 32)->nullable();
            $table->decimal('plan_price', 10, 2)->default(0);
            $table->date('billing_cycle_start_at')->nullable();
            $table->enum('status', ['draft', 'issued', 'paid', 'overdue', 'void'])->default('draft');
            $table->json('invoice_details')->nullable(); // proration breakdown, notes
            $table->timestamps();

            $table->index(['account_id', 'billing_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invoices');
    }
};
