<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_referrals', function (Blueprint $table) {
            $table->id();

            // Who owns the code that was used.
            $table->foreignId('referrer_account_id')->constrained('accounts')->cascadeOnDelete();

            // The invited account. An account can only ever be referred once.
            $table->foreignId('invited_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unique('invited_account_id');

            // Code used at signup (denormalised for auditing).
            $table->string('referral_code', 16);

            // pending -> qualified. (disqualified reserved for future claw-back use.)
            $table->enum('status', ['pending', 'qualified', 'disqualified'])->default('pending');
            $table->timestamp('qualified_at')->nullable();

            // Reward consumption: once a qualified referral has contributed to a granted
            // discount it is marked applied. Applying a discount marks ALL currently
            // unapplied qualified referrals at once (extras are spent, not banked).
            $table->boolean('reward_applied')->default(false);
            $table->timestamp('reward_applied_at')->nullable();
            $table->foreignId('reward_invoice_id')->nullable()->constrained('account_invoices')->nullOnDelete();

            $table->timestamps();

            $table->index(['referrer_account_id', 'status', 'reward_applied'], 'account_referrals_eligibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_referrals');
    }
};
