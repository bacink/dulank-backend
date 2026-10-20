<?php

namespace Database\Seeders;

use App\Models\BankAccountType;
use Illuminate\Database\Seeder;

class BankAccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Rekening Bank', 'status' => 'Active'],
            ['name' => 'Kas Tunai Toko', 'status' => 'Active'],
            ['name' => 'Kas Kecil Kasir', 'status' => 'Active'],
            ['name' => 'E-Wallet & QRIS', 'status' => 'Active'],
            ['name' => 'Rekening Giro Bisnis', 'status' => 'Active'],
        ];

        foreach ($types as $type) {
            BankAccountType::query()->updateOrCreate(
                ['name' => $type['name']],
                ['status' => $type['status']],
            );
        }
    }
}

