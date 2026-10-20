<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Pieces', 'short_name' => 'pcs', 'status' => 'Active'],
            ['name' => 'Lembar', 'short_name' => 'lbr', 'status' => 'Active'],
            ['name' => 'Rim', 'short_name' => 'rim', 'status' => 'Active'],
            ['name' => 'Box / Dus', 'short_name' => 'box', 'status' => 'Active'],
            ['name' => 'Pack / Bungkus', 'short_name' => 'pack', 'status' => 'Active'],
            ['name' => 'Roll', 'short_name' => 'roll', 'status' => 'Active'],
            ['name' => 'Meter', 'short_name' => 'm', 'status' => 'Active'],
            ['name' => 'Meter Persegi', 'short_name' => 'm2', 'status' => 'Active'],
            ['name' => 'Centimeter', 'short_name' => 'cm', 'status' => 'Active'],
            ['name' => 'Kilogram', 'short_name' => 'kg', 'status' => 'Active'],
            ['name' => 'Gram', 'short_name' => 'gr', 'status' => 'Active'],
            ['name' => 'Lusin', 'short_name' => 'lsn', 'status' => 'Active'],
            ['name' => 'Set', 'short_name' => 'set', 'status' => 'Active'],
            ['name' => 'Buku', 'short_name' => 'bku', 'status' => 'Active'],
            ['name' => 'Blok', 'short_name' => 'blk', 'status' => 'Active'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['name' => $unit['name']],
                [
                    'short_name' => $unit['short_name'],
                    'status' => $unit['status'],
                ],
            );
        }
    }
}

