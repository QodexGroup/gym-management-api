<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Until now a row in this table meant "an email job was queued", but the
     * dedupe check read it as "this customer has been emailed". A send that
     * failed in the worker therefore suppressed every later attempt for the
     * whole dedupe window, and the customer silently never got the email.
     *
     * The status column separates the two: queued and sent still suppress a
     * resend, failed does not, so the next scheduler run picks the customer
     * up again. Existing rows are backfilled to sent, which is what the old
     * code effectively claimed about them.
     */
    public function up(): void
    {
        Schema::table('tb_notification_email_logs', function (Blueprint $table) {
            $table->string('status', 10)
                ->default('queued')
                ->after('ref_id')
                ->comment('queued | sent | failed - whether delivery to the mail provider actually succeeded');
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->text('failed_reason')->nullable()->after('sent_at');
        });

        // Rows that predate this column were written at dispatch time and were
        // already treated as sent by the dedupe check, so keep that meaning
        // rather than re-sending historical reminders to every member. sent_at
        // is backfilled from created_at so no row is left claiming it was sent
        // at no particular time.
        DB::table('tb_notification_email_logs')
            ->where('status', 'queued')
            ->update([
                'status' => 'sent',
                'sent_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tb_notification_email_logs', function (Blueprint $table) {
            $table->dropColumn(['status', 'sent_at', 'failed_reason']);
        });
    }
};
