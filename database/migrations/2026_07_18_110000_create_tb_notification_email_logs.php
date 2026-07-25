<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedup log for customer-facing notification emails. Each queued email is
     * recorded here so the email lane can dedupe against its own history,
     * independent of in-app notification records (which can be disabled).
     */
    public function up(): void
    {
        Schema::create('tb_notification_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('type', 50);
            $table->unsignedBigInteger('ref_id')->nullable()->comment('Related record id, e.g. membership id for expiry reminders');
            $table->timestamps();

            $table->index(['account_id', 'type', 'customer_id', 'ref_id', 'created_at'], 'notif_email_log_dedup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_notification_email_logs');
    }
};
