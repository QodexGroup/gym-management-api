<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'tb_class_session_bookings',
        'tb_customer_pt_bookings',
    ];

    private const WITH_COACH_CANCELLED = ['BOOKED', 'ATTENDED', 'NO_SHOW', 'CANCELLED', 'COACH_CANCELLED'];

    private const WITHOUT_COACH_CANCELLED = ['BOOKED', 'ATTENDED', 'NO_SHOW', 'CANCELLED'];

    public function up(): void
    {
        $this->setAllowedStatuses(self::WITH_COACH_CANCELLED);
    }

    public function down(): void
    {
        $this->setAllowedStatuses(self::WITHOUT_COACH_CANCELLED);
    }

    /**
     * Rewrite the allowed values of the booking status column.
     *
     * MySQL stores it as a native ENUM, which only ALTER ... MODIFY can widen.
     * SQLite, which the test suite runs on, has no MODIFY COLUMN at all and
     * renders the same column as a varchar with a CHECK constraint, so the
     * constraint is rebuilt through the schema builder instead. Skipping
     * SQLite entirely is not enough: the CHECK constraint from the create
     * migration would still reject COACH_CANCELLED, which the booking
     * repositories write.
     *
     * @param array<int, string> $statuses
     * @return void
     */
    private function setAllowedStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (self::TABLES as $table) {
                Schema::table($table, function (Blueprint $blueprint) use ($statuses) {
                    $blueprint->enum('status', $statuses)->default('BOOKED')->change();
                });
            }

            return;
        }

        $allowed = implode(',', array_map(static fn (string $status): string => "'{$status}'", $statuses));

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY COLUMN status ENUM({$allowed}) NOT NULL DEFAULT 'BOOKED'");
        }
    }
};
