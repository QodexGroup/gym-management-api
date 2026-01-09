<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        User::updateOrCreate(
            ['firebase_uid' => 'ozrjqD3P8sfSV4v8xtPpKqOH2cp1'],
            [
                'account_id' => 1,
                'firstname' => 'Admin',
                'lastname' => 'User',
                'email' => 'admin@gym.com',
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make('123456'),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        User::where('firebase_uid', 'ozrjqD3P8sfSV4v8xtPpKqOH2cp1')->delete();
    }
};
