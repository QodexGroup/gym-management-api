<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN subscription_status ENUM('trial', 'active', 'trial_expired', 'past_due', 'cancelled', 'locked') DEFAULT 'trial'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN subscription_status ENUM('trial', 'active', 'trial_expired', 'past_due', 'cancelled') DEFAULT 'trial'");
    }
};
