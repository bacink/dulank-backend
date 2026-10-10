<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('DULANK_ADMIN_EMAIL', 'admin@dulank.test');
        $password = (string) env('DULANK_ADMIN_PASSWORD', 'admin12345');

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Dulank Administrator',
                'type' => AccountType::Admin,
                'password' => Hash::make($password),
                'join_date' => now(),
                'status' => 'Active',
            ],
        );

        $admin->syncRoles(['super-admin']);
    }
}
