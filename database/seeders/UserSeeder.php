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
            'password' => Hash::make('matsgm'),
            'is_admin' => true,
        ]);
    }
}
