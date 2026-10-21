<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Standalone fictional foundation fixtures; deliberately excludes the entire product domain. */
class FoundationStagesOneToFiveSeeder extends Seeder
{
    public const SOURCE = 'demo-foundation-1-5';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Foundation fixtures are only allowed in local/testing environments.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::transaction(function (): void {
            $this->settings();
            $districts = $this->regions();
            foreach (['dashboard.view', 'products.manage', 'orders.manage', 'sales.manage', 'purchases.manage', 'finance.manage', 'hrm.manage', 'workflow.manage', 'content.manage', 'settings.manage', 'users.manage', 'peoples.manage', 'reports.view'] as $permission) {
                $this->record(Permission::class, $permission, ['name' => $permission, 'guard_name' => 'web'], []);
            }
            $roles = [];
            foreach (['demo-foundation-manager' => ['dashboard.view', 'peoples.manage', 'hrm.manage'], 'demo-foundation-finance' => ['dashboard.view', 'finance.manage', 'reports.view']] as $name => $permissions) {
                $role = $this->record(Role::class, $name, ['name' => $name, 'guard_name' => 'web'], ['description' => 'Role contoh tahap 1-5; bukan hak akses operasional.'], true);
                if ($role->wasRecentlyCreated) {
                    foreach ($permissions as $permission) {
                        $item = $this->record(Permission::class, $permission, ['name' => $permission, 'guard_name' => 'web'], []);
                        $role->givePermissionTo($item);
                    }
                }
                $roles[] = $role;
            }
            $admins = [];
            foreach (['Ratna Puspitasari', 'Arif Wicaksono'] as $index => $name) {
                $user = $this->record(Models\User::class, 'admin-'.$index, ['email' => 'admin'.($index + 1).'@foundation-demo.test'], ['name' => $name.' (Contoh)', 'type' => 'admin', 'status' => 'Inactive', 'password' => Str::random(64)], true);
                if ($user->wasRecentlyCreated) {
                    $user->assignRole($roles[$index]);
                }
                $admins[] = $user;
            }
            $stores = [];
            foreach (['Karawang Pusat', 'Cikampek'] as $index => $name) {
                $stores[] = $this->record(Models\Store::class, 'store-'.$index, ['store_name' => 'Dulank '.$name.' (Contoh Tahap 1-5)'], [
                    'user_id' => $admins[$index]->id, 'address' => 'Jl. Niaga Contoh No. '.($index + 18).', '.$name.', Karawang',
                    'email' => 'cabang'.($index + 1).'@foundation-demo.test', 'status' => 'Active',
                ], true);
            }
            foreach ($admins as $index => $admin) {
                $this->record(Models\UserAdmin::class, 'admin-profile-'.$index, ['admin_code' => 'DEMO-S15-ADM-00'.($index + 1)], [
                    'user_id' => $admin->id, 'role' => $roles[$index]->name, 'stores' => [(string) $stores[$index]->id],
                    'all_stores' => false, 'status' => 'Inactive', 'descriptions' => 'Akun contoh nonaktif; password acak, bukan akun untuk login.',
                ], true);
            }
            $types = [];
            foreach (['Retail / Umum', 'Reseller', 'Korporat / Instansi'] as $name) {
                $types[] = $this->record(Models\CustomerType::class, 'type-'.$name, ['name' => $name], ['status' => 'Active']);
            }
            foreach (['Rina Handayani', 'Bagus Pratama', 'Dewi Kartika', 'CV Rasa Nusantara', 'PT Kreasi Pangan Karawang', 'Koperasi Sejahtera Cikampek'] as $index => $name) {
                $user = $this->record(Models\User::class, 'customer-user-'.$index, ['email' => 'pelanggan'.($index + 1).'@foundation-demo.test'], [
                    'name' => $name.' (Contoh)', 'type' => 'store', 'status' => 'Inactive', 'password' => Str::random(64), 'company_name' => $index >= 3 ? $name.' (Contoh)' : null,
                ], true);
                $customer = $this->record(Models\Customer::class, 'customer-'.$index, ['code' => 'DEMO-S15-CUS-00'.($index + 1)], [
                    'user_id' => $user->id, 'customer_type_id' => $types[$index % 3]->id, 'channel' => 'Offline', 'date_join' => '2026-09-'.sprintf('%02d', $index + 1),
                ], true);
                $this->address($customer, 'customer-'.$index, $districts[$index % 2], $name.' (Contoh)', $index);
            }
            foreach (['CV Sumber Kertas Mandiri', 'PT Bahan Grafika Sentosa', 'UD Perlengkapan Finishing Jaya'] as $index => $name) {
                $supplier = $this->record(Models\Supplier::class, 'supplier-'.$index, ['code' => 'DEMO-S15-SUP-00'.($index + 1)], [
                    'name' => $name.' (Contoh)', 'email' => 'supplier'.($index + 1).'@foundation-demo.test',
                    'pic_name' => ['Hendra Saputra', 'Maya Anggraini', 'Nina Permata'][$index], 'status' => 'Active', 'date' => '2026-09-01',
                ], true);
                $this->address($supplier, 'supplier-'.$index, $districts[$index % 2], $supplier->name, $index + 6);
            }
            foreach ([['Ratna Puspitasari', 'Administrasi', 'Koordinator Cabang'], ['Arif Wicaksono', 'Keuangan', 'Staf Keuangan'], ['Agus Setiawan', 'Produksi & Percetakan', 'Operator Produksi'], ['Sari Puspita', 'Gudang & Logistik', 'Staf Gudang']] as $index => [$name, $department, $designation]) {
                $dept = $this->record(Models\Department::class, 'department-'.$index, ['name' => $department], ['status' => 'Active']);
                $job = $this->record(Models\Designation::class, 'designation-'.$index, ['name' => $designation], ['status' => 'Active']);
                $this->record(Models\Employee::class, 'employee-'.$index, ['employee_code' => 'DEMO-S15-EMP-00'.($index + 1)], [
                    'name' => $name.' (Contoh)', 'department_id' => $dept->id, 'designation_id' => $job->id, 'user_id' => $admins[$index]->id ?? null,
                    'email' => 'pegawai'.($index + 1).'@foundation-demo.test', 'join_date' => '2026-08-03', 'status' => 'Active',
                    'address' => 'Karawang, Jawa Barat', 'detail_address' => 'Jl. Melati Contoh No. '.($index + 10),
                    'emergency_contact_name' => 'Keluarga '.$name, 'emergency_contact_phone' => null,
                    'emergency_contact2_name' => 'Kontak darurat cadangan '.$name, 'emergency_contact2_phone' => null,
                ], true);
            }
            $cashType = $this->record(Models\BankAccountType::class, 'cash-type', ['name' => 'Kas Tunai Toko'], ['status' => 'Active']);
            foreach ($stores as $index => $store) {
                $this->record(Models\BankAccount::class, 'cash-'.$index, ['account_name' => 'Kas Contoh Tahap 1-5 - '.$store->store_name], [
                    'branch_id' => $store->id, 'bank_account_type_id' => $cashType->id, 'opening_balance' => 0,
                    'description' => 'Kas contoh tanpa nomor rekening, saldo awal atau mutasi nyata.', 'status' => 'Active',
                ], true);
            }
            foreach ([Models\IncomeCategory::class => ['Jasa desain', 'Jasa pengiriman', 'Pendapatan lainnya'], Models\ExpenseCategory::class => ['Listrik operasional', 'Perawatan peralatan', 'Transportasi operasional', 'Perlengkapan kantor']] as $class => $names) {
                foreach ($names as $name) {
                    $this->record($class, $name, ['name' => $name], ['status' => 'Active']);
                }
            }
            $this->record(Models\TaxRate::class, 'tax', ['name' => 'Simulasi Pajak 10% (Contoh Tahap 1-5)'], ['rate' => 10, 'status' => 'Inactive'], true);
            foreach (['printing_shop' => 'CV Mitra Grafika Karawang', 'paper_store' => 'UD Kertas Sejahtera'] as $type => $name) {
                $this->record(Models\Partner::class, 'partner-'.$type, ['type' => $type, 'name' => $name.' (Contoh Tahap 1-5)'], [
                    'province' => 'Jawa Barat', 'city' => 'Kab. Karawang', 'district' => 'Karawang Barat',
                    'address' => 'Jl. Mitra Contoh No. 25, Karawang', 'join_date' => '2026-09-01', 'subscribed' => false, 'status' => 'Inactive',
                ], true);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function settings(): void
    {
        if (! Models\Setting::query()->where('group', 'company')->exists()) {
            $this->record(Models\Setting::class, 'company', ['group' => 'company', 'key' => 'profile'], ['value' => [
                'companyName' => 'PT Dulank Semesta Cida (Contoh)', 'email' => 'kantor@foundation-demo.test',
                'address' => 'Jl. Niaga Contoh No. 18, Karawang, Jawa Barat', 'currency' => 'IDR',
            ]]);
        }
        if (! Models\Setting::query()->whereIn('group', ['currency', 'currencies'])->exists()) {
            $this->record(Models\Setting::class, 'currency', ['group' => 'currency', 'key' => 'items'], ['value' => [[
                'id' => 'IDR', 'code' => 'IDR', 'name' => 'Rupiah Indonesia', 'symbol' => 'Rp', 'exchangeRate' => 1,
                'isDefault' => true, 'status' => 'active', 'createdOn' => '2026-10-21',
            ]]]);
        }
        foreach (['customer_code' => 'CUS', 'supplier' => 'SUP', 'employee_code' => 'EMP', 'user_admin' => 'ADM'] as $key => $prefix) {
            $this->record(Models\Setting::class, 'prefix-'.$key, ['group' => 'prefixes', 'key' => $key], ['value' => $prefix]);
        }
    }

    /** @return array<int, Models\District> */
    private function regions(): array
    {
        $province = $this->record(Models\Province::class, 'province', ['code' => '32'], ['name' => 'Jawa Barat', 'status' => 'Active']);
        $regency = $this->record(Models\Regency::class, 'regency', ['code' => '3215'], ['name' => 'Kab. Karawang', 'province_id' => $province->id, 'type' => 'Kabupaten', 'status' => 'Active']);
        if ((int) $regency->province_id !== (int) $province->id) {
            throw new RuntimeException('Existing regency has a conflicting province; no data was changed.');
        }
        $districts = [];
        foreach (['Karawang Barat' => '41311', 'Cikampek' => '41373'] as $name => $postalCode) {
            $district = $this->record(Models\District::class, 'district-'.$name, ['regency_id' => $regency->id, 'name' => $name], ['code' => 'DEMO-S15-D'.count($districts), 'postal_code' => $postalCode, 'status' => 'Active']);
            $this->record(Models\PostalCode::class, 'postal-'.$name, ['district_id' => $district->id, 'postal_code' => $postalCode, 'village_name' => 'Area contoh cabang '.$name], []);
            $districts[] = $district;
        }

        return $districts;
    }

    private function address(Model $owner, string $key, Models\District $district, string $name, int $index): void
    {
        $regency = $district->regency;
        $this->record(Models\Address::class, $key, ['addressable_type' => $owner->getMorphClass(), 'addressable_id' => $owner->id, 'label' => 'Alamat contoh tahap 1-5'], [
            'province_id' => $regency->province_id, 'regency_id' => $regency->id, 'district_id' => $district->id,
            'recipient_name' => $name, 'name' => 'Alamat utama (Contoh)', 'type' => 'Active', 'street' => 'Jl. Contoh Niaga No. '.($index + 10),
            'district' => $district->name, 'city' => $regency->name, 'province' => $regency->province->name,
            'postal_code' => $district->postal_code, 'country' => 'ID', 'is_default' => true,
        ]);
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    private function record(string $class, string $key, array $identity, array $attributes, bool $reserved = false): Model
    {
        $table = (new $class)->getTable();
        $map = Models\LegacyIdMap::query()->where(['source' => self::SOURCE, 'entity' => $table, 'legacy_id' => $key])->first();
        if ($map !== null) {
            if ($map->model_type !== $class) {
                throw new RuntimeException('Foundation mapping type mismatch: '.$table);
            }

            return $class::query()->findOrFail($map->model_id);
        }
        $query = $class::query();
        foreach ($identity as $column => $value) {
            if ($column === 'name') {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))]);
            } else {
                $query->where($column, $value);
            }
        }
        $existing = $query->first();
        if ($existing !== null && $reserved) {
            throw new RuntimeException('Foundation identity belongs to an untracked record: '.$table);
        }
        $record = $existing ?? $class::query()->create($identity + $attributes);
        if ($record->wasRecentlyCreated) {
            Models\LegacyIdMap::query()->create(['source' => self::SOURCE, 'entity' => $table, 'legacy_id' => $key, 'model_type' => $class, 'model_id' => $record->id]);
        }

        return $record;
    }
}
