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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
