<?php

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;

it('manages suppliers with codes', function () {
    $token = adminToken(adminWithPermissions('purchases.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/suppliers', [
        'supplierId' => 'SUP-900',
        'name' => 'PT Kertas Jaya Makmur',
        'email' => 'info@kertasjaya.co.id',
        'contact' => '+6221-8091234',
        'picName' => 'Budi Santoso',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.supplierId', 'SUP-900')
        ->assertJsonPath('data.name', 'PT Kertas Jaya Makmur')
        ->assertJsonStructure(['data' => ['id', 'supplierId', 'name', 'email', 'contact', 'picName', 'status', 'date']]);

    $this->withToken($token)->getJson('/api/v1/admin/suppliers?search=Kertas')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->putJson('/api/v1/admin/suppliers/SUP-900', [
        'name' => 'PT Kertas Jaya Makmur (Update)',
        'picName' => 'Budi',
    ])->assertOk()->assertJsonPath('data.name', 'PT Kertas Jaya Makmur (Update)');

    $this->withToken($token)->deleteJson('/api/v1/admin/suppliers/SUP-900')
        ->assertOk()
        ->assertJsonPath('data.removed', true);
});

it('creates a purchase, records payable payments and recalculates status', function () {
    $token = adminToken(adminWithPermissions('purchases.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/purchases', [
        'supplier' => 'PT Kertas Jaya Makmur',
        'status' => 'Ordered',
        'items' => [
            ['name' => 'Art Paper 150gsm', 'qty' => 100, 'unit' => 'rim', 'price' => 50000],
        ],
        'shippingCost' => 250000,
        'tax' => 0,
        'paid' => 2000000,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.supplier', 'PT Kertas Jaya Makmur')
        ->assertJsonPath('data.amount', 5250000)
        ->assertJsonPath('data.paid', 2000000)
        ->assertJsonPath('data.due', 3250000)
        ->assertJsonPath('data.paymentStatus', 'Partial')
        ->assertJsonStructure(['data' => ['id', 'noPurchase', 'date', 'supplier', 'status', 'amount', 'paid', 'due', 'paymentStatus', 'items']]);

    $noPurchase = $created->json('data.noPurchase');

    expect($noPurchase)->toStartWith('PR-');

    // Pembayaran utang berikutnya (outflow).
    $this->withToken($token)->postJson("/api/v1/admin/purchases/{$noPurchase}/payments", [
        'amount' => 3250000,
        'method' => 'Transfer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.paid', 5250000)
        ->assertJsonPath('data.due', 0)
        ->assertJsonPath('data.paymentStatus', 'Paid');

    // Melebihi sisa utang ditolak.
    $this->withToken($token)->postJson("/api/v1/admin/purchases/{$noPurchase}/payments", ['amount' => 1000])
        ->assertStatus(422);

    $this->assertDatabaseHas('payments', [
        'payable_type' => Purchase::class,
        'direction' => 'out',
    ]);
});

it('creates purchase orders with items and numbering', function () {
    $token = adminToken(adminWithPermissions('purchases.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/purchase-orders', [
        'supplier' => 'PT Kertas Jaya Makmur',
        'poStatus' => 'Sent',
        'goodsStatus' => 'Pending',
        'termOfPayment' => '30 Days',
        'items' => [
            ['name' => 'HVS A4 80gsm', 'qty' => 50, 'unit' => 'rim', 'price' => 60000],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.poStatus', 'Sent')
        ->assertJsonPath('data.amount', 3000000)
        ->assertJsonPath('data.items.0.name', 'HVS A4 80gsm')
        ->assertJsonStructure(['data' => ['id', 'noPO', 'date', 'supplier', 'amount', 'poStatus', 'goodsStatus', 'items']]);

    expect($created->json('data.noPO'))->toStartWith('PO-');
    expect(Supplier::query()->where('name', 'PT Kertas Jaya Makmur')->exists())->toBeTrue();
});

it('creates a purchase return and receives the supplier refund', function () {
    $token = adminToken(adminWithPermissions('purchases.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/purchase-returns', [
        'noPurchase' => 'PR-100',
        'supplier' => 'PT Kertas Jaya Makmur',
        'items' => [
            ['name' => 'Art Paper 150gsm', 'qty' => 14, 'returnQty' => 2, 'unit' => 'rim', 'price' => 150000, 'reason' => 'Cacat'],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.amount', 300000)
        ->assertJsonPath('data.due', 300000)
        ->assertJsonPath('data.status', 'Pending');

    $noPr = $created->json('data.noPR');

    expect($noPr)->toStartWith('PRT-');

    $this->withToken($token)->postJson("/api/v1/admin/purchase-returns/{$noPr}/payments", [
        'amount' => 300000,
        'method' => 'Transfer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.paid', 300000)
        ->assertJsonPath('data.due', 0)
        ->assertJsonPath('data.status', 'Refunded');

    $this->assertDatabaseHas('payments', [
        'payable_type' => PurchaseReturn::class,
        'direction' => 'in',
    ]);
});

it('requires the purchases permission', function () {
    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/purchases')
        ->assertStatus(403);
});
