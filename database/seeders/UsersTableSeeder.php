<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['firebase_uid' => 'ozrjqD3P8sfSV4v8xtPpKqOH2cp1'],
            [
                'firstname' => 'Admin',
                'lastname' => 'User',
                'email' => 'admin@gym.com',
                'role' => 'admin',
                'password' => Hash::make('123456'),
            ]
        );
    }
}
