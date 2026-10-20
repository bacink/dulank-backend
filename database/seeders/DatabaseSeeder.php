<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed aplikasi.
     *
     * Catatan: data domain store diimpor terpisah melalui
     * `php artisan dulank:import store` (idempoten).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SettingSeeder::class,
            TaxRateSeeder::class,
            RegionSeeder::class,
            DistrictSeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            UnitSeeder::class,
            CustomerTypeSeeder::class,
            BankAccountTypeSeeder::class,
            BankAccountSeeder::class,
            CustomFieldSeeder::class,
            LanguageSeeder::class,
        ]);
    }
}
