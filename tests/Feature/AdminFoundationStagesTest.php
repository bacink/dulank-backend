<?php

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAdmin;
use App\Services\TotpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('rejects suspended admin login and existing tokens', function () {
    $admin = User::factory()->admin()->create(['password' => 'secret123', 'status' => 'Inactive']);
    $token = adminToken($admin);
    $this->postJson('/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'secret123'])->assertUnauthorized();
    $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertForbidden();
});

it('does not allow a store user with an admin ability token into admin routes', function () {
    $user = User::factory()->create();
    $this->withToken(adminToken($user))->getJson('/api/v1/admin/auth/me')->assertForbidden();
});

it('creates an admin with real role permissions and store assignments', function () {
    $token = adminToken(adminWithPermissions('users.manage'));
    $permission = Permission::findOrCreate('peoples.manage', 'web');
    $role = Role::findOrCreate('branch-manager', 'web');
    $role->givePermissionTo($permission);
    $store = Store::query()->create(['store_name' => 'Cabang Utama']);
    $created = $this->withToken($token)->postJson('/api/v1/admin/user-admins', ['name' => 'Manajer', 'email' => 'manager@example.test', 'password' => 'securePassword123', 'role' => $role->name, 'stores' => [(string) $store->id]]);
    $created->assertCreated()->assertJsonPath('data.stores.0', (string) $store->id);
    $user = User::query()->where('email', 'manager@example.test')->firstOrFail();
    expect($user->hasPermissionTo('peoples.manage'))->toBeTrue()->and(Hash::check('securePassword123', $user->password))->toBeTrue();
    $this->withToken($token)->putJson('/api/v1/admin/user-admins/'.$created->json('data.id'), ['status' => 'Inactive'])->assertOk();
    expect($user->refresh()->status)->toBe('Inactive');
});

it('rejects converting an existing customer email into an admin', function () {
    $customer = User::factory()->create(['email' => 'existing@example.test']);
    $token = adminToken(adminWithPermissions('users.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/user-admins', ['name' => 'Override', 'email' => $customer->email, 'role' => 'missing', 'stores' => ['Fake Branch']])->assertUnprocessable();
    expect($customer->refresh()->isStore())->toBeTrue();
    $this->assertDatabaseCount('user_admins', 1);
});

it('limits assigned admins to their store and bank master data', function () {
    $one = Store::query()->create(['store_name' => 'One']);
    $two = Store::query()->create(['store_name' => 'Two']);
    $a = BankAccount::query()->create(['account_name' => 'One cash', 'branch_id' => $one->id]);
    $b = BankAccount::query()->create(['account_name' => 'Two cash', 'branch_id' => $two->id]);
    $admin = adminWithPermissions('peoples.manage', 'finance.manage');
    UserAdmin::query()->where('user_id', $admin->id)->delete();
    UserAdmin::query()->create(['user_id' => $admin->id, 'admin_code' => 'ADM-A', 'role' => 'branch', 'stores' => [(string) $one->id], 'status' => 'Active']);
    $token = adminToken($admin);
    $this->withToken($token)->getJson('/api/v1/admin/stores')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $one->id);
    $this->withToken($token)->getJson('/api/v1/admin/bank-accounts')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson('/api/v1/admin/bank-accounts/'.$b->id.'/statement')->assertNotFound();
    $this->withToken($token)->postJson('/api/v1/admin/bank-accounts', ['accountName' => 'Cross', 'branchId' => $two->id])->assertForbidden();
});

it('changes an actual password only with the correct current password and revokes other sessions', function () {
    $admin = User::factory()->admin()->create(['password' => 'oldPassword123']);
    $current = adminToken($admin);
    adminToken($admin);
    $this->withToken($current)->putJson('/api/v1/admin/profile/password', ['current_password' => 'wrong', 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'])->assertUnprocessable();
    expect(Hash::check('oldPassword123', $admin->refresh()->password))->toBeTrue();
    $this->withToken($current)->putJson('/api/v1/admin/profile/password', ['current_password' => 'oldPassword123', 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'])->assertOk();
    expect(Hash::check('newPassword123', $admin->refresh()->password))->toBeTrue()->and($admin->password_changed_at)->not->toBeNull()->and($admin->tokens()->count())->toBe(1);
});

it('enforces confirmed two-factor authentication at admin login', function () {
    $admin = User::factory()->admin()->create(['password' => 'password123']);
    $token = adminToken($admin);
    $setup = $this->withToken($token)->postJson('/api/v1/admin/account/two-factor/setup', ['password' => 'password123']);
    $setup->assertOk();
    $secret = $setup->json('data.secret');
    $code = app(TotpService::class)->code($secret);
    $this->withToken($token)->postJson('/api/v1/admin/account/two-factor/confirm', ['code' => $code])->assertOk()->assertJsonPath('data.twoFactor', true);
    $this->postJson('/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'password123'])->assertUnprocessable();
    $this->postJson('/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'password123', 'code' => $code])->assertOk();
    $this->withToken($token)->deleteJson('/api/v1/admin/account/two-factor', ['password' => 'wrong'])->assertUnprocessable();
});

it('returns a neutral recovery response and sends only to active admin accounts', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $store = User::factory()->create();
    $this->postJson('/api/v1/admin/auth/forgot-password', ['email' => $admin->email])->assertOk()->assertJsonPath('data.requested', true);
    $this->postJson('/api/v1/admin/auth/forgot-password', ['email' => $store->email])->assertOk()->assertJsonPath('data.requested', true);
    Notification::assertSentTo($admin, ResetPassword::class);
    Notification::assertNotSentTo($store, ResetPassword::class);
});

it('resets a password with a valid expiring token and prevents replay', function () {
    $admin = User::factory()->admin()->create();
    adminToken($admin);
    $token = Password::createToken($admin);
    $payload = ['email' => $admin->email, 'token' => $token, 'password' => 'resetPassword123', 'password_confirmation' => 'resetPassword123'];
    $this->postJson('/api/v1/admin/auth/reset-password', $payload)->assertOk();
    expect(Hash::check('resetPassword123', $admin->refresh()->password))->toBeTrue()->and($admin->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/admin/auth/reset-password', $payload)->assertUnprocessable();
});

it('does not expose shared security settings as actual account state', function () {
    $admin = adminWithPermissions('settings.manage');
    Setting::query()->create(['group' => 'security', 'key' => 'twoFactor', 'value' => true]);
    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/account/security')->assertOk()->assertJsonPath('data.twoFactor', false)->assertJsonPath('data.email', $admin->email);
    $this->withToken(adminToken($admin))->putJson('/api/v1/admin/settings/security', ['twoFactor' => true])->assertUnprocessable();
});

it('uses the seeded company profile shape and preserves other profile fields on updates', function () {
    Setting::query()->create(['group' => 'company', 'key' => 'profile', 'value' => ['companyName' => 'Dulank', 'phone' => '123']]);
    $token = adminToken(adminWithPermissions('settings.manage'));
    $this->withToken($token)->getJson('/api/v1/admin/settings/company')->assertOk()->assertJsonPath('data.values.companyName', 'Dulank');
    $this->withToken($token)->putJson('/api/v1/admin/settings/company', ['companyName' => 'Dulank Revisi'])->assertOk()->assertJsonPath('data.values.phone', '123');
});

it('rejects missing or invalid region parents instead of choosing the first record', function () {
    Province::query()->create(['name' => 'First', 'code' => '01']);
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/regencies', ['name' => 'Invalid', 'provinceId' => 999])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/v1/admin/regencies', ['name' => 'Missing'])->assertUnprocessable();
    $this->assertDatabaseCount('regencies', 0);
});

it('stores canonical region parents and persists inactive statuses', function () {
    $province = Province::query()->create(['name' => 'West', 'code' => '01']);
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $created = $this->withToken($token)->postJson('/api/v1/admin/regencies', ['name' => 'City', 'provinceId' => $province->id, 'status' => 'Inactive']);
    $created->assertCreated()->assertJsonPath('data.provinceId', (string) $province->id)->assertJsonPath('data.status', 'Inactive');
    $this->withToken($token)->postJson('/api/v1/admin/districts', ['name' => 'District', 'regencyId' => $created->json('data.id'), 'status' => 'Inactive'])->assertCreated()->assertJsonPath('data.status', 'Inactive');
    $this->withToken($token)->putJson('/api/v1/admin/regencies/'.$created->json('data.id'), ['name' => 'City Revised'])->assertOk()->assertJsonPath('data.status', 'Inactive');
    $this->withToken($token)->deleteJson('/api/v1/admin/provinces/'.$province->id)->assertConflict();
});

it('rejects normalized duplicate reference names and preserves the referenced master', function () {
    $department = Department::query()->create(['name' => 'Produksi']);
    Employee::query()->create(['employee_code' => 'EMP-A', 'name' => 'Operator', 'department_id' => $department->id]);
    $token = adminToken(adminWithPermissions('hrm.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/departments', ['name' => ' produksi '])->assertUnprocessable();
    $this->withToken($token)->deleteJson('/api/v1/admin/departments/'.$department->id)->assertConflict();
    $this->assertDatabaseHas('departments', ['id' => $department->id]);
});

it('never edits an existing account when creating a customer with the same email', function () {
    $user = User::factory()->admin()->create(['email' => 'protected@example.test', 'name' => 'Protected']);
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/customers', ['name' => 'Replacement', 'email' => $user->email])->assertUnprocessable();
    expect($user->refresh()->name)->toBe('Protected');
    $this->assertDatabaseCount('customers', 0);
});

it('creates and edits a customer using type ID with offline origin and unpredictable password', function () {
    $type = CustomerType::query()->create(['name' => 'Corporate']);
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $created = $this->withToken($token)->postJson('/api/v1/admin/customers', ['name' => 'Company', 'email' => 'company@example.test', 'customerTypeId' => $type->id]);
    $created->assertCreated()->assertJsonPath('data.customerTypeId', (string) $type->id)->assertJsonPath('data.channel', 'Offline');
    expect(Hash::check('password', Customer::query()->first()->user->password))->toBeFalse();
});

it('validates polymorphic owners and geographic parent chains', function () {
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/addresses', ['ownerType' => 'customer', 'ownerId' => 999])->assertUnprocessable();
    $customer = Customer::query()->create(['code' => 'CUS-A']);
    $a = Province::query()->create(['name' => 'A', 'code' => '01']);
    $b = Province::query()->create(['name' => 'B', 'code' => '02']);
    $regency = Regency::query()->create(['province_id' => $a->id, 'name' => 'City', 'code' => '03']);
    $this->withToken($token)->postJson('/api/v1/admin/addresses', ['ownerType' => 'customer', 'ownerId' => $customer->id, 'provinceId' => $b->id, 'regencyId' => $regency->id])->assertUnprocessable();
    $this->assertDatabaseCount('addresses', 0);
});

it('persists employee IDs and both emergency contacts and supports actual updates', function () {
    $department = Department::query()->create(['name' => 'Produksi']);
    $token = adminToken(adminWithPermissions('hrm.manage'));
    $created = $this->withToken($token)->postJson('/api/v1/admin/employees', ['name' => 'Operator', 'departmentId' => $department->id, 'contact1Name' => 'Kontak Satu', 'contact2Name' => 'Kontak Dua']);
    $created->assertCreated()->assertJsonPath('data.contact1Name', 'Kontak Satu')->assertJsonPath('data.contact2Name', 'Kontak Dua');
    $this->withToken($token)->putJson('/api/v1/admin/employees/'.$created->json('data.id'), ['name' => 'Operator Revisi', 'departmentId' => $department->id, 'contact2Name' => 'Revisi'])->assertOk()->assertJsonPath('data.contact2Name', 'Revisi');
    $this->assertDatabaseCount('employees', 1);
});

it('links employees only to distinct admin accounts and rejects customer accounts', function () {
    $token = adminToken(adminWithPermissions('hrm.manage'));
    $customer = User::factory()->create(['type' => 'store']);
    $admin = User::factory()->admin()->create();
    $this->withToken($token)->postJson('/api/v1/admin/employees', ['name' => 'Invalid', 'userId' => $customer->id])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/v1/admin/employees', ['name' => 'Operator', 'userId' => $admin->id])->assertCreated()->assertJsonPath('data.userId', (string) $admin->id);
    $this->withToken($token)->postJson('/api/v1/admin/employees', ['name' => 'Duplicate', 'userId' => $admin->id])->assertUnprocessable();
});

it('validates prefixes and requires one active default currency', function () {
    $token = adminToken(adminWithPermissions('settings.manage'));
    $this->withToken($token)->putJson('/api/v1/admin/settings/prefixes', ['customer_code' => 'BAD VALUE'])->assertUnprocessable();
    $this->withToken($token)->putJson('/api/v1/admin/settings/prefixes', ['customer_code' => 'CUS'])->assertOk();
    $currency = ['code' => 'IDR', 'exchangeRate' => 1, 'isDefault' => false, 'status' => 'active'];
    $this->withToken($token)->putJson('/api/v1/admin/settings/currency', ['items' => [$currency]])->assertUnprocessable();
    $currency['isDefault'] = true;
    $this->withToken($token)->putJson('/api/v1/admin/settings/currency', ['items' => [$currency]])->assertOk();
    $currency['exchangeRate'] = 0;
    $this->withToken($token)->putJson('/api/v1/admin/settings/currency', ['items' => [$currency]])->assertUnprocessable();
});

it('preserves compatible unit updates while preventing cross-branch admin grants', function () {
    $admin = adminWithPermissions('users.manage', 'products.manage');
    $one = Store::query()->create(['store_name' => 'One']);
    $two = Store::query()->create(['store_name' => 'Two']);
    $profile = UserAdmin::query()->where('user_id', $admin->id)->firstOrFail();
    $profile->update(['all_stores' => false, 'stores' => [(string) $one->id]]);
    $role = Role::findOrCreate('branch-staff', 'web');
    $token = adminToken($admin);
    $unit = $this->withToken($token)->postJson('/api/v1/admin/units', ['name' => 'Lembar']);
    $unit->assertCreated();
    $this->withToken($token)->postJson('/api/v1/admin/units', ['id' => $unit->json('data.id'), 'name' => 'Lembar', 'shortName' => 'lbr'])->assertOk();
    $this->withToken($token)->postJson('/api/v1/admin/user-admins', ['name' => 'Cross', 'email' => 'cross@example.test', 'password' => 'securePassword123', 'role' => $role->name, 'stores' => [(string) $two->id]])->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'cross@example.test']);
});

it('lets HR read active location choices without permission to mutate geographic masters', function () {
    Province::query()->create(['name' => 'Active', 'code' => '01', 'status' => 'Active']);
    Province::query()->create(['name' => 'Inactive', 'code' => '02', 'status' => 'Inactive']);
    $token = adminToken(adminWithPermissions('hrm.manage'));
    $this->withToken($token)->getJson('/api/v1/admin/location-options')->assertOk()->assertJsonCount(1, 'data.provinces')->assertJsonPath('data.provinces.0.name', 'Active');
    $this->withToken($token)->postJson('/api/v1/admin/provinces', ['name' => 'Forbidden'])->assertForbidden();
});

it('rejects unknown or ambiguous category labels without implicitly creating a master', function () {
    $token = adminToken(adminWithPermissions('products.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/sub-categories', ['category' => 'Typo Category', 'name' => 'Box'])->assertUnprocessable();
    $this->assertDatabaseCount('categories', 0);
    $category = Category::query()->create(['name' => 'Packaging', 'code' => 'PKG', 'slug' => 'packaging']);
    $this->withToken($token)->postJson('/api/v1/admin/sub-categories', ['category' => ' packaging ', 'name' => 'Box'])->assertCreated();
    $this->assertDatabaseHas('sub_categories', ['category_id' => $category->id, 'name' => 'Box']);
});
