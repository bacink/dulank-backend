<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Retail / Umum', 'status' => 'Active'],
            ['name' => 'Reseller', 'status' => 'Active'],
            ['name' => 'Agen Percetakan', 'status' => 'Active'],
            ['name' => 'Korporat / Instansi', 'status' => 'Active'],
            ['name' => 'Member VIP', 'status' => 'Active'],
        ];

        foreach ($types as $type) {
            CustomerType::query()->updateOrCreate(
                ['name' => $type['name']],
                ['status' => $type['status']],
            );
        }
    }
}

