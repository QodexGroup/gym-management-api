<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic per-account metered-resource table. One row per (account, resource).
 * Keeps consumption counters off the `accounts` table so new metered features
 * (SMS credits, etc.) are added as new `resource_key` values — no schema change.
 *
 * used_amount / limit_override are in the resource's base unit
 * (storage: KB; future sms_credits: credits).
 */
return new class extends Migration
{
    /**
     * Create the account_usages meter table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('account_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('resource_key', 50); // e.g. 'storage', 'sms_credits'
            $table->decimal('used_amount', 20, 2)->default(0);
            $table->decimal('limit_override', 20, 2)->nullable(); // null = use config default
            $table->timestamps();

            $table->unique(['account_id', 'resource_key']);
            $table->index('resource_key');
        });
    }

    /**
     * Drop the account_usages meter table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('account_usages');
    }
};
