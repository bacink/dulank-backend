<?php

use App\Models\Order;

it('creates a delivery note linked to a sale and manages its items', function () {
    $order = Order::factory()->create([
        'number' => 'SO-501',
        'contact' => ['name' => 'PT. Maju Jaya'],
        'shipping_address' => [
            'street' => 'Jl. Arif Rahman Hakim',
            'district' => 'TELUK JAMBE TIMUR',
            'city' => 'KAB. KARAWANG',
            'province' => 'JAWA BARAT',
            'postalCode' => '41360',
        ],
    ]);

    $token = adminToken(adminWithPermissions('orders.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/delivery-notes', [
        'noSales' => 'SO-501',
        'status' => 'Ordered',
        'po' => 'PO-991',
        'items' => [
            ['description' => 'Brosur A4', 'qty' => 100, 'unit' => 'rim', 'packingQty' => '2 pack', 'weight' => '10 kg'],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.noSales', 'SO-501')
        ->assertJsonPath('data.customer', 'PT. Maju Jaya')
        ->assertJsonPath('data.status', 'Ordered')
        ->assertJsonPath('data.items.0.description', 'Brosur A4')
        ->assertJsonStructure(['data' => ['id', 'dnNo', 'date', 'customer', 'noSales', 'shippingAddress', 'status', 'dateStatus', 'po', 'shippingBy', 'reference', 'items']]);

    $dnNo = $created->json('data.dnNo');

    expect($dnNo)->toStartWith('DN-');

    $this->assertDatabaseHas('delivery_notes', ['dn_no' => $dnNo, 'order_id' => $order->id]);

    $this->withToken($token)->putJson("/api/v1/admin/delivery-notes/{$dnNo}", [
        'status' => 'Complete',
        'receiveBy' => 'Resepsionis',
        'items' => [
            ['description' => 'Brosur A4', 'qty' => 100, 'unit' => 'rim'],
            ['description' => 'Poster A2', 'qty' => 20, 'unit' => 'lembar'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Complete')
        ->assertJsonPath('data.receiveBy', 'Resepsionis')
        ->assertJsonCount(2, 'data.items');

    $this->withToken($token)->getJson('/api/v1/admin/delivery-notes?search='.$dnNo)
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->deleteJson("/api/v1/admin/delivery-notes/{$dnNo}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('delivery_notes', ['dn_no' => $dnNo]);
});
