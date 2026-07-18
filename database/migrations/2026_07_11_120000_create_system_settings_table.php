<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic per-account key/value settings store (EAV).
     * The natural key is (account_id, set_key) — no surrogate id.
     */
    public function up(): void
    {
        if (Schema::hasTable('account_system_settings')) {
            return;
        }

        Schema::create('account_system_settings', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('set_key', 100);
            $table->text('set_value')->nullable();
            $table->timestamps();

            $table->primary(['account_id', 'set_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_system_settings');
    }
};
