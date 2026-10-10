<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

it('returns sales report aggregates and dashboard summary', function () {
    $order = Order::factory()->create([
        'number' => 'SO-RPT-1',
        'total' => 1000000,
        'paid' => 400000,
        'due' => 600000,
        'ordered_at' => now(),
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Brosur', 'quantity' => 100, 'total' => 1000000]);
    Order::factory()->create(['number' => 'SO-RPT-2', 'total' => 500000, 'ordered_at' => now()->subDays(10)]);

    $token = adminToken(adminWithPermissions('reports.view'));

    $this->withToken($token)->getJson('/api/v1/admin/reports/sales?startDate='.now()->subDay()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.totalOrders', 1)
        ->assertJsonPath('data.totalSales', 1000000)
        ->assertJsonPath('data.topProducts.0.name', 'Brosur')
        ->assertJsonStructure(['data' => ['totalOrders', 'totalSales', 'totalPaid', 'totalDue', 'byChannel', 'byStatus', 'topProducts']]);

    $this->withToken($token)->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.ordersToday', 1)
        ->assertJsonPath('data.revenueToday', 1000000)
        ->assertJsonStructure(['data' => ['ordersToday', 'revenueToday', 'ordersTotal', 'pendingPayments', 'openTickets', 'totalBalance', 'lowStock', 'recentOrders', 'topProducts']]);
});

it('returns product and customer reports', function () {
    Product::factory()->create(['quantity' => 5, 'quantity_alert' => 10, 'status' => 'Active']);

    $token = adminToken(adminWithPermissions('reports.view'));

    $this->withToken($token)->getJson('/api/v1/admin/reports/products')
        ->assertOk()
        ->assertJsonPath('data.totalProducts', 1)
        ->assertJsonPath('data.lowStock.0.quantity', 5)
        ->assertJsonStructure(['data' => ['totalProducts', 'activeProducts', 'lowStock', 'topSelling']]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/customers')
        ->assertOk()
        ->assertJsonStructure(['data' => ['totalCustomers', 'topCustomers']]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/finance')
        ->assertOk()
        ->assertJsonStructure(['data' => ['totalIncome', 'totalExpense', 'totalReceivable', 'totalPayable', 'totalInflow', 'totalOutflow', 'pendingPayments']]);
});

it('exports a csv report', function () {
    Order::factory()->create(['number' => 'SO-CSV-1', 'ordered_at' => now()]);

    $response = $this->withToken(adminToken(adminWithPermissions('reports.view')))
        ->get('/api/v1/admin/reports/sales/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('rejects an unknown report type and enforces the permission', function () {
    $this->withToken(adminToken(adminWithPermissions('reports.view')))
        ->getJson('/api/v1/admin/reports/unknown')
        ->assertStatus(404);

    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/reports/sales')
        ->assertStatus(403);
});
