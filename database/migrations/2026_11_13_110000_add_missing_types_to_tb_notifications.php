<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notification type column never grew past the three types it was
     * created with, while NotificationConstant did.
     *
     * import_completed has been written by NotificationService since the
     * client importer shipped, and the column has been rejecting it ever
     * since. The insert sits inside a try/catch that logs and swallows, so
     * the import itself still succeeded and nothing surfaced - the user
     * simply never got the notification.
     *
     * report_email_failed is new: it is how a report that could not be
     * emailed reaches the user, the email that would have told them being
     * the thing that failed.
     */
    private const TYPES_AFTER = [
        'membership_expiring',
        'payment_received',
        'customer_registered',
        'import_completed',
        'report_email_failed',
    ];

    private const TYPES_BEFORE = [
        'membership_expiring',
        'payment_received',
        'customer_registered',
    ];

    public function up(): void
    {
        $this->setAllowedTypes(self::TYPES_AFTER);
    }

    /**
     * Rows carrying one of the new types would violate the narrower column,
     * so they are cleared first. They are notifications, not records of
     * record, and the alternative is a migration that cannot run.
     */
    public function down(): void
    {
        DB::table('tb_notifications')
            ->whereIn('type', array_diff(self::TYPES_AFTER, self::TYPES_BEFORE))
            ->delete();

        $this->setAllowedTypes(self::TYPES_BEFORE);
    }

    /**
     * Rewrite the allowed values of the type column.
     *
     * MySQL holds it as a native ENUM that only ALTER ... MODIFY can widen;
     * SQLite renders it as a varchar with a CHECK constraint and has no
     * MODIFY COLUMN, so the constraint is rebuilt through the schema builder.
     *
     * @param array<int, string> $types
     * @return void
     */
    private function setAllowedTypes(array $types): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('tb_notifications', function (Blueprint $blueprint) use ($types) {
                $blueprint->enum('type', $types)->change();
            });

            return;
        }

        $allowed = implode(',', array_map(static fn (string $type): string => "'{$type}'", $types));

        DB::statement("ALTER TABLE tb_notifications MODIFY COLUMN type ENUM({$allowed}) NOT NULL");
    }
};
