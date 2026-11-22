<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@qcash.test'],
            [
                'name' => 'QCash Admin',
                'phone_number' => '254790000000',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => 'password',
                'is_admin' => true,
                'admin_activated_at' => now(),
            ],
        );
    }
}
