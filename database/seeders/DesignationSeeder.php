<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        $designations = [
            ['name' => 'Operator Mesin Offset', 'status' => 'Active'],
            ['name' => 'Operator Digital Printing & Banner', 'status' => 'Active'],
            ['name' => 'Operator Finishing & Pond', 'status' => 'Active'],
            ['name' => 'Operator Laminasi & Hot Print', 'status' => 'Active'],
            ['name' => 'Graphic Designer & Setting CTP', 'status' => 'Active'],
            ['name' => 'Kasir POS & Customer Service', 'status' => 'Active'],
            ['name' => 'Admin Gudang & Stok', 'status' => 'Active'],
            ['name' => 'Staf Keuangan & Pajak', 'status' => 'Active'],
            ['name' => 'Sales Executive B2B', 'status' => 'Active'],
            ['name' => 'Supervisor Produksi', 'status' => 'Active'],
            ['name' => 'Kepala Toko / Store Manager', 'status' => 'Active'],
        ];

        foreach ($designations as $desig) {
            Designation::query()->updateOrCreate(
                ['name' => $desig['name']],
                ['status' => $desig['status']],
            );
        }
    }
}

