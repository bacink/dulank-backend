<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('dulank.documents') as $type => $prefix) {
            Setting::query()->updateOrCreate(
                ['group' => 'prefixes', 'key' => $type],
                ['value' => ['prefix' => $prefix]],
            );
        }

        Setting::query()->updateOrCreate(
            ['group' => 'company', 'key' => 'profile'],
            ['value' => [
                'name' => 'PT Dulank Semesta Cida',
                'email' => 'info@dulank.test',
                'phone' => null,
                'address' => null,
                'npwp' => null,
            ]],
        );
    }
}
