<?php

use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

it('persists organization members status counts and actual CRUD', function (string $resource, string $foreignKey) {
    $token = adminToken(adminWithPermissions('hrm.manage'));
    $one = Employee::query()->create(['name' => 'Same Name', 'employee_code' => 'EMP-ONE']);
    $two = Employee::query()->create(['name' => 'Same Name', 'employee_code' => 'EMP-TWO']);
    $created = $this->withToken($token)->postJson('/api/v1/admin/'.$resource, ['name' => 'Tim Baru', 'memberIds' => [(string) $one->id], 'status' => 'Active']);
    $created->assertCreated()->assertJsonPath('data.memberIds', [(string) $one->id])->assertJsonPath('data.totalMembers', 1);
    $id = $created->json('data.id');
    expect($one->refresh()->getAttribute($foreignKey))->toBe((int) $id);
    $this->withToken($token)->putJson('/api/v1/admin/'.$resource.'/'.$id, ['name' => 'Tim Revisi', 'memberIds' => [(string) $two->id], 'status' => 'Inactive'])->assertOk()->assertJsonPath('data.members', ['Same Name']);
    expect($one->refresh()->getAttribute($foreignKey))->toBeNull();
    expect($two->refresh()->getAttribute($foreignKey))->toBe((int) $id);
    $this->withToken($token)->getJson('/api/v1/admin/'.$resource)->assertOk()->assertJsonPath('data.0.name', 'Tim Revisi')->assertJsonPath('data.0.status', 'Inactive')->assertJsonPath('data.0.memberIds', [(string) $two->id]);
    $this->withToken($token)->deleteJson('/api/v1/admin/'.$resource.'/'.$id)->assertConflict();
    $this->withToken($token)->putJson('/api/v1/admin/'.$resource.'/'.$id, ['name' => 'Tim Revisi', 'memberIds' => []])->assertOk()->assertJsonPath('data.totalMembers', 0);
    $this->withToken($token)->deleteJson('/api/v1/admin/'.$resource.'/'.$id)->assertOk();
    $this->assertDatabaseMissing($resource, ['id' => $id]);
})->with(['department' => ['departments', 'department_id'], 'designation' => ['designations', 'designation_id']]);

it('rejects invalid member IDs before changing a department or employee', function () {
    $department = Department::query()->create(['name' => 'Asli']);
    $employee = Employee::query()->create(['name' => 'Employee', 'employee_code' => 'EMP-KEEP', 'department_id' => $department->id]);
    $token = adminToken(adminWithPermissions('hrm.manage'));
    $this->withToken($token)->putJson('/api/v1/admin/departments/'.$department->id, ['name' => 'Jangan berubah', 'memberIds' => [99999]])->assertUnprocessable();
    expect($department->refresh()->name)->toBe('Asli');
    expect($employee->refresh()->department_id)->toBe($department->id);
});

it('stores customer and supplier addresses with numeric owner IDs instead of business codes', function (string $kind) {
    $owner = $kind === 'customer' ? Customer::query()->create(['code' => 'CUS-DEMO']) : Supplier::query()->create(['code' => 'SUP-DEMO', 'name' => 'Supplier']);
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/addresses', ['ownerType' => $kind, 'ownerId' => $owner->code, 'detailAddress' => 'Jl. Niaga 20'])->assertUnprocessable();
    $created = $this->withToken($token)->postJson('/api/v1/admin/addresses', ['ownerType' => $kind, 'ownerId' => (string) $owner->id, 'name' => 'Penerima', 'detailAddress' => 'Jl. Niaga 20', 'status' => 'Home']);
    $created->assertCreated()->assertJsonPath('data.ownerId', (string) $owner->id)->assertJsonPath('data.ownerType', $kind);
    $this->assertDatabaseHas('addresses', ['addressable_type' => $owner->getMorphClass(), 'addressable_id' => $owner->id, 'street' => 'Jl. Niaga 20']);
})->with(['customer', 'supplier']);

it('uploads and persists a store photo and can clear it while keeping its inactive assigned manager', function () {
    Storage::fake('public');
    $token = adminToken(adminWithPermissions('peoples.manage'));
    $uploaded = $this->withToken($token)->postJson('/api/v1/admin/uploads/image', ['file' => UploadedFile::fake()->image('store.jpg'), 'folder' => 'stores']);
    $uploaded->assertCreated();
    $photo = $uploaded->json('data.url');
    Storage::disk('public')->assertExists($uploaded->json('data.path'));
    $manager = User::factory()->admin()->create(['status' => 'Inactive']);
    $store = Store::query()->create(['store_name' => 'Cabang Lama', 'user_id' => $manager->id]);
    $this->withToken($token)->putJson('/api/v1/admin/stores/'.$store->id, ['storeName' => 'Cabang Lama', 'managerId' => $manager->id, 'photo' => $photo])->assertOk();
    $this->withToken($token)->getJson('/api/v1/admin/stores')->assertOk()->assertJsonPath('data.0.photo', $photo);
    $this->withToken($token)->putJson('/api/v1/admin/stores/'.$store->id, ['storeName' => 'Cabang Revisi'])->assertOk();
    expect($store->refresh()->photo_path)->toBe($photo);
    $this->withToken($token)->putJson('/api/v1/admin/stores/'.$store->id, ['storeName' => 'Cabang Revisi', 'photo' => null])->assertOk();
    expect($store->refresh()->photo_path)->toBeNull();
    $this->withToken($token)->postJson('/api/v1/admin/stores', ['storeName' => 'Cabang Baru', 'managerId' => $manager->id])->assertUnprocessable();
});

it('persists customer and supplier photos and preserves customer account status on edit', function () {
    $token = adminToken(adminWithPermissions('peoples.manage', 'purchases.manage'));
    $user = User::factory()->create(['status' => 'Inactive']);
    $customer = Customer::query()->create(['code' => 'CUS-IMG', 'user_id' => $user->id]);
    $photo = '/storage/uploads/customer.jpg';
    $this->withToken($token)->putJson('/api/v1/admin/customers/'.$customer->id, ['name' => $user->name, 'email' => $user->email, 'photo' => $photo])->assertOk()->assertJsonPath('data.photo', $photo)->assertJsonPath('data.status', 'Inactive');
    expect($user->refresh()->status)->toBe('Inactive');
    $supplier = $this->withToken($token)->postJson('/api/v1/admin/suppliers', ['name' => 'Supplier Photo', 'photo' => '/storage/uploads/supplier.jpg']);
    $supplier->assertCreated()->assertJsonPath('data.photo', '/storage/uploads/supplier.jpg');
    $this->withToken($token)->putJson('/api/v1/admin/suppliers/'.$supplier->json('data.supplierId'), ['name' => 'Supplier Edited', 'photo' => null])->assertOk()->assertJsonPath('data.photo', null);
});

it('exposes why an assigned role cannot be deleted and still allows deletion of an unused role', function () {
    $token = adminToken(adminWithPermissions('users.manage'));
    $used = Role::findOrCreate('used-role', 'web');
    $empty = Role::findOrCreate('empty-role', 'web');
    $user = User::factory()->admin()->create();
    $user->assignRole($used);
    $response = $this->withToken($token)->getJson('/api/v1/admin/roles')->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', (string) $used->id);
    expect($row['canDelete'])->toBeFalse();
    expect($row['usersCount'])->toBe(1);
    expect($row['deleteReason'])->toContain('1 pengguna');
    $this->withToken($token)->deleteJson('/api/v1/admin/roles/'.$used->id)->assertConflict();
    $this->withToken($token)->deleteJson('/api/v1/admin/roles/'.$empty->id)->assertOk();
    expect($user->refresh()->hasRole('used-role'))->toBeTrue();
});
