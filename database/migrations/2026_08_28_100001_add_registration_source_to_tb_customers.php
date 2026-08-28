<?php

use App\Constant\CustomerRegistrationSourceConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record how each member profile was created.
     *
     * Public registrations are written straight into tb_customers with no
     * approval step, so this column is what lets staff tell self-registered
     * members apart from staff-created ones — to review them, and to clean up
     * a spam wave without touching legitimate records.
     */
    public function up(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->string('registration_source', 20)
                ->default(CustomerRegistrationSourceConstant::STAFF)
                ->after('qr_code_uuid');

            $table->index(['account_id', 'registration_source']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'registration_source']);
            $table->dropColumn('registration_source');
        });
    }
};
