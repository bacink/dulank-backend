<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        TaxRate::query()->updateOrCreate(
            ['name' => 'PPN'],
            ['rate' => (float) config('dulank.tax_rate_default', 11), 'status' => 'Active'],
        );
    }
}
