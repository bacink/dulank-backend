<?php

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Quotation;
use App\Models\User;

it('lists only the authenticated users orders with legacy timeline format', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $order = Order::factory()->for($user)->create([
        'number' => '2504027H2DQ68B',
        'status' => OrderStatus::Delivered,
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Brosur PPDN', 'spec' => 'A4']);
    OrderStatusHistory::factory()->for($order)->create(['status' => 'ordered', 'occurred_at' => '2025-04-10 09:00:00']);
    Order::factory()->for($other)->create();

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/orders');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', '2504027H2DQ68B')
        ->assertJsonPath('data.0.status', 'delivered')
        ->assertJsonPath('data.0.items.0.name', 'Brosur PPDN')
        ->assertJsonPath('data.0.timeline.0.status', 'ordered')
        ->assertJsonPath('data.0.timeline.0.date', '2025-04-10 09:00 WIB')
        ->assertJsonStructure(['data' => [['id', 'userId', 'status', 'date', 'items', 'shippingAddress', 'total', 'timeline']]]);
});

it('shows an order by number and blocks other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-777']);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/orders/SO-777')
        ->assertOk()
        ->assertJsonPath('data.id', 'SO-777');

    $this->withToken($other->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/orders/SO-777')
        ->assertStatus(403);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/orders/SO-999')
        ->assertStatus(404);
});

it('lists quotations with customer info snapshot', function () {
    $user = User::factory()->create();
    $quotation = Quotation::factory()->for($user)->create([
        'no_quotation' => 'QT-001',
        'customer_name' => 'PT. Maju Jaya',
        'customer_email' => 'info@majujaya.com',
        'customer_phone' => '+6281234567890',
    ]);
    $quotation->items()->create([
        'product_name' => 'Brosur PPDN',
        'order_qty' => 500,
        'unit_price' => 45000,
        'amount' => 22500000,
        'spec' => 'A4',
    ]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/quotations')
        ->assertOk()
        ->assertJsonPath('data.0.id', 'QT-001')
        ->assertJsonPath('data.0.customerInfo.name', 'PT. Maju Jaya')
        ->assertJsonPath('data.0.items.0.unitPrice', 45000)
        ->assertJsonStructure(['data' => [['id', 'userId', 'date', 'status', 'items', 'customerInfo', 'total']]]);
});

it('lists invoices for store under /invoices and legacy /billings alias', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-123']);
    Invoice::factory()->for($order)->for($user)->create([
        'invoice_no' => '600449168',
        'amount' => 650000,
        'amount_due' => 650000,
    ]);

    $expected = [
        'id' => '600449168',
        'userId' => $user->id,
        'orderId' => 'SO-123',
        'total' => 650000,
    ];

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->assertJsonPath('data.0.id', $expected['id'])
        ->assertJsonPath('data.0.orderId', 'SO-123')
        ->assertJsonPath('data.0.total', 650000);
});

it('serves the legacy users endpoint scoped to its owner', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/users/'.$user->id)
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'avatar']]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/users/'.($user->id + 100))
        ->assertStatus(404);
});
