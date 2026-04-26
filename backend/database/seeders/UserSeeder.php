<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email'=> 'admin@journo.com',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'name' => 'Tuan Tu',
            'email'=> 'tuantu@journo.com',
            'password' => bcrypt('password'),
        ]);
    }
}