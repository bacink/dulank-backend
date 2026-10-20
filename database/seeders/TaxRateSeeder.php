<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['name' => 'PPN 11%', 'rate' => 11.00, 'status' => 'Active'],
            ['name' => 'Bebas Pajak (0%)', 'rate' => 0.00, 'status' => 'Active'],
            ['name' => 'PPh 21 (Jasa Tenaga Ahli 2.5%)', 'rate' => 2.50, 'status' => 'Active'],
            ['name' => 'PPh 23 (Jasa Cetak/Maklon 2%)', 'rate' => 2.00, 'status' => 'Active'],
        ];

        foreach ($taxes as $tax) {
            TaxRate::query()->updateOrCreate(
                ['name' => $tax['name']],
                ['rate' => $tax['rate'], 'status' => $tax['status']],
            );
        }
    }
}
