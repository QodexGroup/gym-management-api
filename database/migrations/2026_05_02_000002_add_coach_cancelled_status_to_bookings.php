<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tb_class_session_bookings MODIFY COLUMN status ENUM('BOOKED','ATTENDED','NO_SHOW','CANCELLED','COACH_CANCELLED') NOT NULL DEFAULT 'BOOKED'");
        DB::statement("ALTER TABLE tb_customer_pt_bookings MODIFY COLUMN status ENUM('BOOKED','ATTENDED','NO_SHOW','CANCELLED','COACH_CANCELLED') NOT NULL DEFAULT 'BOOKED'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tb_class_session_bookings MODIFY COLUMN status ENUM('BOOKED','ATTENDED','NO_SHOW','CANCELLED') NOT NULL DEFAULT 'BOOKED'");
        DB::statement("ALTER TABLE tb_customer_pt_bookings MODIFY COLUMN status ENUM('BOOKED','ATTENDED','NO_SHOW','CANCELLED') NOT NULL DEFAULT 'BOOKED'");
    }
};
