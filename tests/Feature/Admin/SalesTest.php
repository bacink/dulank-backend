<?php

use App\Models\Order;
use App\Models\Product;

it('creates a POS sale and records the payment', function () {
    $product = Product::factory()->create(['name' => 'Kartu Nama', 'price_from' => 50000]);

    $token = adminToken(adminWithPermissions('sales.manage'));

    $response = $this->withToken($token)->postJson('/api/v1/admin/sales', [
        'customer' => 'Walk-in Customer',
        'items' => [
            ['productId' => $product->id, 'name' => $product->name, 'quantity' => 2, 'price' => 50000, 'unit' => 'box'],
        ],
        'paidAmount' => 111000,
        'method' => 'Cash',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.channel', 'POS')
        ->assertJsonPath('data.subTotal', 100000)
        ->assertJsonPath('data.tax', 11000)
        ->assertJsonPath('data.total', 111000)
        ->assertJsonPath('data.status', 'Paid')
        ->assertJsonPath('data.method', 'Cash')
        ->assertJsonStructure(['data' => ['id', 'saleNo', 'customer', 'date', 'subTotal', 'deliveryFee', 'discount', 'tax', 'total', 'delivery', 'channel', 'status', 'method', 'items', 'payments']]);

    $order = Order::query()->firstOrFail();

    expect($order->channel->value)->toBe('pos')
        ->and($order->payment_status->value)->toBe('Paid')
        ->and($order->paid)->toBe(111000)
        ->and($order->due)->toBe(0);
});

it('records additional payments and voids a sale', function () {
    $order = Order::factory()->create([
        'number' => 'SO-401',
        'total' => 100000,
        'due' => 100000,
        'paid' => 0,
        'payment_status' => 'Unpaid',
    ]);

    $token = adminToken(adminWithPermissions('sales.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/sales/SO-401/payments', [
        'amount' => 60000,
        'method' => 'Bank Transfer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'Partial')
        ->assertJsonPath('data.paid', 60000)
        ->assertJsonPath('data.due', 40000);

    $this->withToken($token)->postJson('/api/v1/admin/sales/SO-401/payments', [
        'amount' => 200000,
    ])->assertStatus(422);

    $this->withToken($token)->postJson('/api/v1/admin/sales/SO-401/void', ['note' => 'Salah input'])
        ->assertOk()
        ->assertJsonPath('data.canonicalStatus', 'cancelled');

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => 'cancelled',
    ]);

    $this->withToken($token)->getJson('/api/v1/admin/sales?channel=POS')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});
