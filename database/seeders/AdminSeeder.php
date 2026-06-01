<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@career.ai'],
            [
                'name'        => 'Admin',
                'password'    => Hash::make('password123'),
                'role'        => 'admin',
                'governorate' => 'Damascus',
            ]
        );

        echo "Admin user created: admin@career.ai / password123\n";
    }
}
