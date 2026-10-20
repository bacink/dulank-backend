<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Produksi & Percetakan', 'status' => 'Active'],
            ['name' => 'Desain Grafis & CTP', 'status' => 'Active'],
            ['name' => 'Finishing & Pasca Cetak', 'status' => 'Active'],
            ['name' => 'Kasir & Front Office', 'status' => 'Active'],
            ['name' => 'Gudang & Logistik', 'status' => 'Active'],
            ['name' => 'Keuangan & Akuntansi', 'status' => 'Active'],
            ['name' => 'Pemasaran & Penjualan', 'status' => 'Active'],
            ['name' => 'SDM & Operasional Umum', 'status' => 'Active'],
        ];

        foreach ($departments as $dept) {
            Department::query()->updateOrCreate(
                ['name' => $dept['name']],
                ['status' => $dept['status']],
            );
        }
    }
}

