<?php

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;

it('manages customers with derived balance and auto user', function () {
    $token = adminToken(adminWithPermissions('peoples.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/customers', [
        'name' => 'Dafa Alfauzi',
        'email' => 'dafa@example.test',
        'type' => 'VIP',
        'phone' => '0812345678',
        'channel' => 'Mobile App',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Dafa Alfauzi')
        ->assertJsonPath('data.type', 'VIP')
        ->assertJsonPath('data.balance', 0)
        ->assertJsonStructure(['data' => ['id', 'customerId', 'name', 'email', 'type', 'phone', 'channel', 'dateJoin', 'balance', 'status']]);

    $this->assertDatabaseHas('users', ['email' => 'dafa@example.test', 'type' => 'store']);

    $customer = Customer::query()->firstOrFail();

    CustomerAccountEntry::query()->create([
        'customer_id' => $customer->id,
        'source_type' => 'sale',
        'amount' => 500000,
        'occurred_at' => now(),
    ]);
    CustomerAccountEntry::query()->create([
        'customer_id' => $customer->id,
        'source_type' => 'payment',
        'amount' => 200000,
        'occurred_at' => now(),
    ]);

    $this->withToken($token)->getJson('/api/v1/admin/customers?search=Dafa')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.balance', 300000);

    $this->withToken($token)->putJson("/api/v1/admin/customers/{$customer->id}", [
        'name' => 'Dafa Alfauzi Update',
        'email' => 'dafa@example.test',
        'type' => 'VIP',
    ])->assertOk()->assertJsonPath('data.name', 'Dafa Alfauzi Update');

    $this->assertDatabaseHas('customer_types', ['name' => 'VIP']);
});

it('manages customer types and stores', function () {
    $token = adminToken(adminWithPermissions('peoples.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/customer-types', ['name' => 'Reguler'])->assertCreated();
    $this->withToken($token)->getJson('/api/v1/admin/customer-types')->assertOk()->assertJsonFragment(['name' => 'Reguler']);

    $created = $this->withToken($token)->postJson('/api/v1/admin/stores', [
        'storeName' => 'Kacetak Pusat Karawang',
        'address' => 'Karawang Barat',
        'phone' => '+62 812 0000',
        'email' => 'pusat@example.test',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.storeName', 'Kacetak Pusat Karawang');

    $store = Store::query()->firstOrFail();

    $this->withToken($token)->putJson("/api/v1/admin/stores/{$store->id}", [
        'storeName' => 'Kacetak Pusat (Update)',
        'status' => 'Inactive',
    ])->assertOk()->assertJsonPath('data.storeName', 'Kacetak Pusat (Update)');

    $this->withToken($token)->getJson('/api/v1/admin/stores?search=Kacetak')
        ->assertOk()
        ->assertJsonPath('data.0.storeName', 'Kacetak Pusat (Update)');
});

it('manages addresses polymorphic for customers and suppliers', function () {
    $token = adminToken(adminWithPermissions('peoples.manage'));

    $customer = Customer::query()->create(['code' => 'CUS-1', 'channel' => 'Online', 'date_join' => now()]);
    $supplier = Supplier::query()->create(['code' => 'SUP-1', 'name' => 'PT Kertas Jaya']);

    $customerAddress = $this->withToken($token)->postJson('/api/v1/admin/addresses', [
        'ownerType' => 'customer',
        'ownerId' => $customer->id,
        'name' => 'Rumah Dafa',
        'contact' => '0812345678',
        'province' => 'JAWA BARAT',
        'city' => 'KAB. KARAWANG',
        'district' => 'TELUK JAMBE TIMUR',
        'detailAddress' => 'Jl. Arif Rahman Hakim No. 1',
        'postalCode' => '41360',
    ]);

    $customerAddress->assertCreated()
        ->assertJsonPath('data.ownerType', 'customer')
        ->assertJsonPath('data.name', 'Rumah Dafa')
        ->assertJsonStructure(['data' => ['id', 'ownerType', 'ownerId', 'name', 'contact', 'province', 'city', 'district', 'detailAddress', 'postalCode', 'status']]);

    $this->withToken($token)->postJson('/api/v1/admin/addresses', [
        'ownerType' => 'supplier',
        'ownerId' => $supplier->id,
        'name' => 'Gudang Supplier',
        'fullAddress' => 'Kawasan Industri',
        'province' => 'DKI JAKARTA',
        'city' => 'JAKARTA PUSAT',
    ])
        ->assertCreated()
        ->assertJsonPath('data.ownerType', 'supplier');

    $this->withToken($token)->getJson('/api/v1/admin/addresses?type=customer')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->getJson('/api/v1/admin/addresses/stats')
        ->assertOk()
        ->assertJsonPath('data.totalAddress', 2)
        ->assertJsonStructure(['data' => ['totalAddress', 'totalProvince', 'totalCity', 'totalPosCode']]);
});

it('manages members and user admins', function () {
    $token = adminToken(adminWithPermissions('users.manage'));

    $member = User::factory()->create(['email' => 'member@example.test', 'status' => 'Active']);
    Customer::query()->create(['user_id' => $member->id, 'code' => 'CUS-M1', 'channel' => 'Online', 'date_join' => now()]);

    $this->withToken($token)->getJson('/api/v1/admin/users?search=member@example.test')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'Active Member')
        ->assertJsonPath('data.0.customerId', 'CUS-M1');

    $this->withToken($token)->putJson("/api/v1/admin/users/{$member->id}", [
        'status' => 'Suspended',
        'verifiedEmail' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Suspended')
        ->assertJsonPath('data.verifiedEmail', true);

    $created = $this->withToken($token)->postJson('/api/v1/admin/user-admins', [
        'name' => 'Admin Dafa',
        'email' => 'admin.dafa@example.test',
        'role' => 'Manager',
        'stores' => ['Kacetak Pusat'],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Admin Dafa')
        ->assertJsonPath('data.role', 'Manager')
        ->assertJsonPath('data.stores.0', 'Kacetak Pusat');

    expect($created->json('data.adminCode'))->toStartWith('ADM-');

    $this->assertDatabaseHas('users', ['email' => 'admin.dafa@example.test', 'type' => 'admin']);

    $adminId = $created->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/user-admins/{$adminId}", [
        'role' => 'Supervisor',
    ])->assertOk()->assertJsonPath('data.role', 'Supervisor');

    $this->withToken($token)->deleteJson("/api/v1/admin/user-admins/{$adminId}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);
});

it('requires peoples permission for people endpoints', function () {
    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/customers')
        ->assertStatus(403);
});
