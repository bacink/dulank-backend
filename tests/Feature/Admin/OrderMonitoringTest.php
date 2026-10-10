<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

it('lists and filters orders with admin status mapping', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'number' => 'SO-301',
        'status' => OrderStatus::AwaitingPayment,
        'contact' => ['name' => 'Ahmad Dhani'],
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Brosur']);
    Order::factory()->for($user)->create(['number' => 'SO-302', 'status' => OrderStatus::Delivered]);

    $token = adminToken(adminWithPermissions('orders.manage'));

    $this->withToken($token)->getJson('/api/v1/admin/orders')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data' => [['id', 'no', 'customer', 'orderDate', 'status', 'salesChannel', 'items', 'totalAmount']]]);

    $this->withToken($token)->getJson('/api/v1/admin/orders?status=Waiting')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->withToken($token)->getJson('/api/v1/admin/orders?status=Complete')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.no', 'SO-302');

    $this->withToken($token)->getJson('/api/v1/admin/orders?search=301')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'Processing');
});

it('updates an order status with timeline and statusBy', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-303', 'status' => OrderStatus::Pending]);

    $admin = adminWithPermissions('orders.manage');

    $this->withToken(adminToken($admin))
        ->postJson('/api/v1/admin/orders/SO-303/status', ['status' => 'Processing', 'note' => 'Masuk produksi'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Processing')
        ->assertJsonPath('data.canonicalStatus', 'production')
        ->assertJsonPath('data.statusBy', $admin->name);

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => 'production',
        'note' => 'Masuk produksi',
    ]);
});

it('returns order statistics', function () {
    $user = User::factory()->create();
    Order::factory()->count(3)->for($user)->create();
    Order::factory()->for($user)->create(['status' => OrderStatus::Delivered]);
    Order::factory()->for($user)->create(['status' => OrderStatus::Cancelled]);

    $this->withToken(adminToken(adminWithPermissions('orders.manage')))
        ->getJson('/api/v1/admin/orders/stats')
        ->assertOk()
        ->assertJsonPath('data.totalOrders', 5)
        ->assertJsonPath('data.totalComplete', 1)
        ->assertJsonPath('data.totalCancel', 1)
        ->assertJsonPath('data.totalCustomers', 1);
});

it('requires the orders permission for monitoring endpoints', function () {
    Order::factory()->create();

    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/orders')
        ->assertStatus(403);
});
