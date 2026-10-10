<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission dasar (matrix lengkap menyusul di Fase 3).
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

        $superAdmin = Role::query()->firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        foreach ($this->permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin->syncPermissions($this->permissions);
        $superAdmin->syncPermissions($this->permissions);
    }
}
