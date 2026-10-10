<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\PostalCode;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seed master wilayah dari sumber data mock:
 * - store: public/json/provinsi.json, kota-kabupaten.json, kodepos.json
 * - admin: data/districts.json (nama kecamatan; admin hanya punya 26 baris)
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $storePath = (string) config('dulank.import.store_path');
        $adminPath = (string) config('dulank.import.admin_path');

        $this->seedProvinces($storePath.'/public/json/provinsi.json');
        $this->seedRegencies($storePath.'/public/json/kota-kabupaten.json');
        $this->seedDistricts($adminPath.'/data/districts.json');
        $this->seedPostalCodes($storePath.'/public/json/kodepos.json');
    }

    private function seedProvinces(string $path): void
    {
        if (! File::exists($path)) {
            $this->command?->warn("RegionSeeder: file tidak ditemukan {$path}");

            return;
        }

        /** @var array<string, string> $rows */
        $rows = json_decode(File::get($path), true) ?? [];

        foreach ($rows as $code => $name) {
            Province::query()->updateOrCreate(
                ['code' => (string) $code],
                ['name' => (string) $name, 'status' => 'Active'],
            );
        }

        $this->command?->info('RegionSeeder: '.count($rows).' provinsi.');
    }

    private function seedRegencies(string $path): void
    {
        if (! File::exists($path)) {
            $this->command?->warn("RegionSeeder: file tidak ditemukan {$path}");

            return;
        }

        /** @var array<string, string> $rows */
        $rows = json_decode(File::get($path), true) ?? [];

        $provinceIds = Province::query()->pluck('id', 'code');
        $count = 0;

        foreach ($rows as $code => $name) {
            $code = (string) $code;
            $provinceId = $provinceIds[substr($code, 0, 2)] ?? null;

            if ($provinceId === null) {
                continue;
            }

            Regency::query()->updateOrCreate(
                ['code' => $code],
                [
                    'province_id' => $provinceId,
                    'name' => (string) $name,
                    'type' => str_starts_with((string) $name, 'Kota') ? 'Kota' : 'Kabupaten',
                ],
            );

            $count++;
        }

        $this->command?->info("RegionSeeder: {$count} kabupaten/kota.");
    }

    private function seedDistricts(string $path): void
    {
        if (! File::exists($path)) {
            $this->command?->warn("RegionSeeder: file tidak ditemukan {$path}");

            return;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(File::get($path), true) ?? [];

        $regencyIds = Regency::query()
            ->join('provinces', 'provinces.id', '=', 'regencies.province_id')
            ->selectRaw('regencies.id, regencies.name as regency_name, provinces.name as province_name')
            ->get()
            ->keyBy(fn ($row) => mb_strtolower($row->province_name.'|'.$this->normalizeRegionName($row->regency_name)));

        $count = 0;

        foreach ($rows as $row) {
            $key = mb_strtolower(($row['province'] ?? '').'|'.$this->normalizeRegionName((string) ($row['regency'] ?? '')));
            $regencyId = $regencyIds[$key]->id ?? null;

            if ($regencyId === null) {
                continue;
            }

            District::query()->updateOrCreate(
                ['regency_id' => $regencyId, 'name' => (string) ($row['name'] ?? '')],
                ['postal_code' => $row['postalCode'] ?? null],
            );

            $count++;
        }

        $this->command?->info("RegionSeeder: {$count} kecamatan (admin).");
    }

    private function normalizeRegionName(string $name): string
    {
        return trim((string) preg_replace('/^(kab\.?|kota)\s+/i', '', $name));
    }

    private function seedPostalCodes(string $path): void
    {
        if (! File::exists($path)) {
            $this->command?->warn("RegionSeeder: file tidak ditemukan {$path}");

            return;
        }

        /** @var array<string, array{bps?: string, nama?: string}> $rows */
        $rows = json_decode(File::get($path), true) ?? [];

        PostalCode::query()->truncate();

        $now = now();
        $chunks = array_chunk($rows, 500, true);

        foreach ($chunks as $chunk) {
            $insert = [];
            foreach ($chunk as $postalCode => $meta) {
                $insert[] = [
                    'postal_code' => (string) $postalCode,
                    'village_name' => (string) ($meta['nama'] ?? ''),
                    'bps_district_code' => isset($meta['bps']) ? (string) $meta['bps'] : null,
                    'district_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('postal_codes')->insert($insert);
        }

        $this->command?->info('RegionSeeder: '.count($rows).' kode pos.');
    }
}
