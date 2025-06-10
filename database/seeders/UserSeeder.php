<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Michael',
            'email' => 'gurovm@gmail.com',
            'password' => Hash::make('ts3w4dpq'),
            'is_admin' => true,
        ]);
        User::create([
            'name' => 'Eran',
            'email' => 'eran@erate.co.il',
            'password' => Hash::make('t48r2ue8'),
            'is_admin' => true,
        ]);
        User::create([
            'name' => 'Eldad',
            'email' => 'eldad@alea-m.com',
            'password' => Hash::make('9s7ez5rj'),
            'is_admin' => false,
            'is_report' => true,
        ]);
    }
}
