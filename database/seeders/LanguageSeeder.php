<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            [
                'code' => 'id',
                'name' => 'Bahasa Indonesia',
                'flag' => '🇮🇩',
                'rtl' => false,
                'progress' => 100,
                'status' => 'Active',
                'translations' => [
                    'dashboard' => 'Dasbor',
                    'sales' => 'Penjualan',
                    'orders' => 'Pesanan',
                    'inventory' => 'Inventaris',
                    'customers' => 'Pelanggan',
                    'settings' => 'Pengaturan',
                    'reports' => 'Laporan',
                    'add_new' => 'Tambah Baru',
                    'save' => 'Simpan',
                    'edit' => 'Ubah',
                    'delete' => 'Hapus',
                    'search' => 'Cari...',
                    'status' => 'Status',
                    'active' => 'Aktif',
                    'inactive' => 'Nonaktif',
                ],
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'flag' => '🇺🇸',
                'rtl' => false,
                'progress' => 95,
                'status' => 'Active',
                'translations' => [
                    'dashboard' => 'Dashboard',
                    'sales' => 'Sales',
                    'orders' => 'Orders',
                    'inventory' => 'Inventory',
                    'customers' => 'Customers',
                    'settings' => 'Settings',
                    'reports' => 'Reports',
                    'add_new' => 'Add New',
                    'save' => 'Save',
                    'edit' => 'Edit',
                    'delete' => 'Delete',
                    'search' => 'Search...',
                    'status' => 'Status',
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ],
            ],
        ];

        foreach ($languages as $item) {
            $lang = Language::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'flag' => $item['flag'],
                    'rtl' => $item['rtl'],
                    'progress' => $item['progress'],
                    'status' => $item['status'],
                ],
            );

            foreach ($item['translations'] as $key => $val) {
                $lang->translations()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $val],
                );
            }
        }
    }
}

