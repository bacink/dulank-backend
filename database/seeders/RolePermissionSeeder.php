<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Matriks permission lengkap seluruh modul Dulank Admin.
     *
     * @var array<int, string>
     */
    private array $permissions = [
        'dashboard.view',
        'products.manage',
        'orders.manage',
        'sales.manage',
        'purchases.manage',
        'finance.manage',
        'hrm.manage',
        'workflow.manage',
        'content.manage',
        'settings.manage',
        'users.manage',
        'peoples.manage',
        'reports.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        $rolesData = [
            [
                'name' => 'super-admin',
                'description' => 'Super Administrator dengan hak akses tertinggi ke seluruh fitur dan pengaturan sistem.',
                'permissions' => $this->permissions,
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator Toko pengelola operasional umum, katalog, pesanan, dan pelanggan.',
                'permissions' => $this->permissions,
            ],
            [
                'name' => 'cashier',
                'description' => 'Kasir POS bertugas memproses transaksi kasir, input order langsung, dan cetak struk pembayaran.',
                'permissions' => [
                    'dashboard.view',
                    'sales.manage',
                    'orders.manage',
                    'peoples.manage',
                ],
            ],
            [
                'name' => 'operator',
                'description' => 'Operator Produksi bertugas mengeksekusi SPK pengerjaan cetak/finishing dan update progres kerja.',
                'permissions' => [
                    'dashboard.view',
                    'workflow.manage',
                    'orders.manage',
                ],
            ],
            [
                'name' => 'designer',
                'description' => 'Desainer Grafis bertugas setting file cetak, pembuatan plat CTP, dan approval materi desain.',
                'permissions' => [
                    'dashboard.view',
                    'workflow.manage',
                    'content.manage',
                ],
            ],
            [
                'name' => 'finance',
                'description' => 'Staf Keuangan mengelola penagihan invoice, kas masuk/keluar, pembayaran supplier, dan penggajian.',
                'permissions' => [
                    'dashboard.view',
                    'finance.manage',
                    'reports.view',
                    'hrm.manage',
                ],
            ],
            [
                'name' => 'warehouse',
                'description' => 'Staf Gudang & Logistik mengontrol stok bahan baku kertas, penerimaan barang PO, dan surat jalan.',
                'permissions' => [
                    'dashboard.view',
                    'purchases.manage',
                    'products.manage',
                    'orders.manage',
                ],
            ],
        ];

        foreach ($rolesData as $roleInfo) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleInfo['name'], 'guard_name' => 'web'],
                ['description' => $roleInfo['description']]
            );

            $role->forceFill(['description' => $roleInfo['description']])->save();
            $role->syncPermissions($roleInfo['permissions']);
        }
    }
}
