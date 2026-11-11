<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nama' => 'Admin Koperasi',
                'email' => 'admin@kspkaryaduta.test',
                'password' => Hash::make('password123'),
                'no_telepon' => '081234567890',
            ]
        );
    }
}
