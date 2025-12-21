<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->default(1)->after('id');
            $table->string('firebase_uid')->nullable();
            $table->renameColumn('name', 'firstname');
            $table->string('lastname')->nullable();
            $table->string('email')->nullable()->change();
            $table->enum('role', ['admin', 'staff', 'coach'])->default('admin');
            $table->string('password')->nullable()->change();
            $table->string('phone')->nullable();
            $table->enum('status', ['active', 'deactivated'])->default('active');
            $table->softDeletes();

            // Drop the old unique constraint on email only
            $table->dropUnique(['email']);

            // Create a composite unique constraint on (email, account_id)
            // This allows the same email in different accounts
            // Soft-deleted users are handled by application validation (whereNull('deleted_at'))
            $table->unique(['email', 'account_id'], 'users_email_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('users_email_account_unique');

            // Restore the old unique constraint on email only
            $table->unique('email');

            // Remove added columns
            $table->dropColumn([
                'account_id',
                'firebase_uid',
                'lastname',
                'role',
                'phone',
                'status',
                'deleted_at'
            ]);

            // Rename firstname back to name
            $table->renameColumn('firstname', 'name');

            // Restore email and password to not nullable
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
