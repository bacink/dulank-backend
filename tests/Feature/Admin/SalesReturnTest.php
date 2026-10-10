<?php

use App\Models\Order;
use App\Models\SalesReturn;

it('creates a sales return with computed totals and refunds it', function () {
    $order = Order::factory()->create(['number' => 'SO-601', 'contact' => ['name' => 'PT. Maju Jaya']]);

    $token = adminToken(adminWithPermissions('orders.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/sales-returns', [
        'salesNo' => 'SO-601',
        'items' => [
            ['name' => 'Brosur A4', 'qtyOrder' => 100, 'qtyReturn' => 10, 'unit' => 'rim', 'price' => 5000, 'reason' => 'Rusak'],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.salesNo', 'SO-601')
        ->assertJsonPath('data.customer', 'PT. Maju Jaya')
        ->assertJsonPath('data.paymentStatus', 'Unpaid')
        ->assertJsonPath('data.total', 50000)
        ->assertJsonPath('data.items.0.returnAmount', 50000)
        ->assertJsonStructure(['data' => ['id', 'returnNo', 'date', 'salesNo', 'customer', 'paymentStatus', 'total', 'items']]);

    $returnNo = $created->json('data.returnNo');

    expect($returnNo)->toStartWith('SR-');

    $this->withToken($token)->putJson("/api/v1/admin/sales-returns/{$returnNo}", [
        'items' => [
            ['name' => 'Brosur A4', 'qtyOrder' => 100, 'qtyReturn' => 20, 'unit' => 'rim', 'price' => 5000, 'reason' => 'Rusak'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.total', 100000);

    $this->withToken($token)->postJson("/api/v1/admin/sales-returns/{$returnNo}/refund", [
        'method' => 'Transfer',
    ])
        ->assertOk()
        ->assertJsonPath('data.paymentStatus', 'Paid')
        ->assertJsonPath('data.paymentMethod', 'Transfer')
        ->assertJsonStructure(['data' => ['paymentDate', 'payment' => ['method', 'amount']]]);

    $return = SalesReturn::query()->where('return_no', $returnNo)->firstOrFail();

    expect($return->refundPayment)->not->toBeNull()
        ->and($return->refundPayment->direction->value)->toBe('out')
        ->and($return->refundPayment->amount)->toBe(100000);

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => 'return-refunded',
    ]);

    // Retur yang sudah dibayar tidak bisa direfund/diubah lagi.
    $this->withToken($token)->postJson("/api/v1/admin/sales-returns/{$returnNo}/refund", ['method' => 'Transfer'])
        ->assertStatus(409);

    $this->withToken($token)->putJson("/api/v1/admin/sales-returns/{$returnNo}", [
        'items' => [['name' => 'X', 'qtyReturn' => 1]],
    ])->assertStatus(422);
});
