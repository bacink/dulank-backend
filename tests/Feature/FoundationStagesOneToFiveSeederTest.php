<?php

use App\Models;
use Database\Seeders\FoundationStagesOneToFiveSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

it('seeds connected foundation masters with inactive accounts and zero financial balances', function () {
    Notification::fake();
    $this->seed(FoundationStagesOneToFiveSeeder::class);

    $this->assertDatabaseCount('stores', 2);
    $this->assertDatabaseCount('user_admins', 2);
    $this->assertDatabaseCount('customers', 6);
    $this->assertDatabaseCount('suppliers', 3);
    $this->assertDatabaseCount('addresses', 9);
    $this->assertDatabaseCount('employees', 4);
    $this->assertDatabaseCount('partners', 2);
    $this->assertDatabaseCount('bank_accounts', 2);
    $this->assertDatabaseCount('postal_codes', 2);
    expect(Models\User::query()->where('status', '!=', 'Inactive')->count())->toBe(0);
    expect(Models\UserAdmin::query()->where('all_stores', true)->count())->toBe(0);
    expect(Models\BankAccount::query()->sum('opening_balance'))->toBe(0);
    $profile = Models\UserAdmin::query()->firstOrFail();
    expect($profile->user->hasRole('demo-foundation-manager'))->toBeTrue();
    $store = Models\Store::query()->findOrFail($profile->stores[0]);
    expect($store->user_id)->toBe($profile->user_id);
    $employee = Models\Employee::query()->where('user_id', $profile->user_id)->firstOrFail();
    expect($employee->department)->not->toBeNull();
    expect($employee->designation)->not->toBeNull();
    expect($employee->emergency_contact2_name)->not->toBeNull();
    $customer = Models\Customer::query()->firstOrFail();
    expect($customer->customerType)->not->toBeNull();
    expect($customer->channel->value ?? $customer->channel)->toBe('Offline');
    $address = Models\Address::query()->firstOrFail();
    $district = Models\District::query()->findOrFail($address->district_id);
    expect($address->regency_id)->toBe($district->regency_id);
    expect($address->province_id)->toBe($district->regency->province_id);
    foreach (['orders', 'payments', 'invoices', 'purchases', 'bank_ledger_entries', 'customer_account_entries', 'employee_salaries', 'payslips', 'job_orders', 'personal_access_tokens'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Notification::assertNothingSent();
});

it('never creates or changes product catalogue production inventory or promotional data', function () {
    $product = Models\Product::factory()->create(['name' => 'Produk milik tim produk', 'price' => 12000]);
    $domains = config('data_contracts.masters');
    $tables = array_unique(array_merge($domains['catalog'], $domains['purchasing'], $domains['printing'], $domains['production'], $domains['promotion'], ['listings', 'partner_metrics']));
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $this->seed(FoundationStagesOneToFiveSeeder::class);
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($rows);
    }
    expect($product->refresh()->name)->toBe('Produk milik tim produk');
});

it('reruns without duplicate rows and preserves existing settings masters and edited fixtures', function () {
    $department = Models\Department::query()->create(['name' => ' keuangan ', 'status' => 'Inactive']);
    Models\Setting::query()->create(['group' => 'company', 'key' => 'companyName', 'value' => 'Perusahaan pengguna']);
    Models\Setting::query()->create(['group' => 'currencies', 'key' => 'default', 'value' => ['code' => 'USD', 'rate' => 1]]);
    Models\Setting::query()->create(['group' => 'prefixes', 'key' => 'supplier', 'value' => 'VENDOR']);
    $this->seed(FoundationStagesOneToFiveSeeder::class);
    $store = Models\Store::query()->firstOrFail();
    $store->update(['store_name' => 'Nama cabang hasil edit']);
    $account = Models\User::query()->where('email', 'admin1@foundation-demo.test')->firstOrFail();
    $account->update(['name' => 'Nama hasil edit', 'status' => 'Active']);
    $role = Role::findByName('demo-foundation-manager', 'web');
    $role->syncPermissions([]);
    $before = [];
    foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), false) as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $this->seed(FoundationStagesOneToFiveSeeder::class);
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($rows);
    }
    expect($department->refresh()->status)->toBe('Inactive');
    expect($store->refresh()->store_name)->toBe('Nama cabang hasil edit');
    expect($account->refresh()->status)->toBe('Active');
    $this->assertDatabaseMissing('settings', ['group' => 'company', 'key' => 'profile']);
    $this->assertDatabaseMissing('settings', ['group' => 'currency']);
});

it('rolls back all fixture writes when a reserved identity belongs to a real account', function () {
    $user = Models\User::factory()->admin()->create(['email' => 'admin1@foundation-demo.test', 'name' => 'Akun asli']);
    $original = $user->refresh()->getRawOriginal();
    expect(fn () => $this->seed(FoundationStagesOneToFiveSeeder::class))->toThrow(RuntimeException::class, 'untracked record');
    expect($user->refresh()->getRawOriginal())->toBe($original);
    $this->assertDatabaseCount('users', 1);
    foreach (['stores', 'settings', 'roles', 'permissions', 'legacy_id_map', 'provinces', 'customers'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

it('refuses conflicting geographic parents without silently relinking existing data', function () {
    $province = Models\Province::query()->create(['code' => '31', 'name' => 'Provinsi existing', 'status' => 'Active']);
    $regency = Models\Regency::query()->create(['code' => '3215', 'name' => 'Kabupaten existing', 'province_id' => $province->id]);
    expect(fn () => $this->seed(FoundationStagesOneToFiveSeeder::class))->toThrow(RuntimeException::class, 'conflicting province');
    expect($regency->refresh()->province_id)->toBe($province->id);
    $this->assertDatabaseCount('provinces', 1);
    $this->assertDatabaseCount('settings', 0);
});

it('rejects production execution before writing anything', function () {
    $previous = app()->environment();
    app()->instance('env', 'production');
    try {
        expect(fn () => (new FoundationStagesOneToFiveSeeder)->run())->toThrow(RuntimeException::class, 'local/testing');
        $this->assertDatabaseCount('settings', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('legacy_id_map', 0);
    } finally {
        app()->instance('env', $previous);
    }
});
