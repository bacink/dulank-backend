<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;

it('returns dashboard KPIs, recent orders, top products and monthly trend', function () {
    $user = User::factory()->create();

    $order = Order::factory()->for($user)->create([
        'number' => 'SO-501',
        'total' => 1000000,
        'paid' => 500000,
        'due' => 500000,
    ]);

    OrderItem::factory()->for($order)->create(['name' => 'Brosur A4', 'quantity' => 12]);
    Order::factory()->for($user)->create([
        'number' => 'SO-502',
        'status' => OrderStatus::Cancelled,
        'total' => 250000,
    ]);

    SalesReturn::query()->create(['return_no' => 'SR-701', 'date' => now()->toDateString(), 'total' => 50000]);
    Purchase::query()->create(['no_purchase' => 'PO-701', 'date' => now()->toDateString(), 'amount' => 300000]);
    PurchaseReturn::query()->create(['no_pr' => 'PR-701', 'date' => now()->toDateString(), 'amount' => 20000]);
    Income::query()->create(['no' => 'INC-701', 'date' => now()->toDateString(), 'name' => 'Pendapatan Lain', 'amount' => 100000]);
    Expense::query()->create(['no_expense' => 'EXP-701', 'date' => now()->toDateString(), 'name' => 'Operasional', 'amount' => 40000, 'paid' => 40000]);
    Invoice::query()->create(['invoice_no' => 'INV-701', 'invoice_date' => now()->toDateString(), 'amount_due' => 75000]);
    Customer::query()->create(['code' => 'CST-701']);
    Supplier::query()->create(['code' => 'SUP-701', 'name' => 'PT Kertas Sejahtera', 'date' => now()->toDateString()]);

    $token = adminToken(adminWithPermissions('reports.view'));

    $this->withToken($token)->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.ordersToday', 2)
        ->assertJsonPath('data.ordersTotal', 2)
        // Order dibatalkan tidak dihitung sebagai penjualan.
        ->assertJsonPath('data.totalSales', 1000000)
        ->assertJsonPath('data.totalSalesReturn', 50000)
        ->assertJsonPath('data.totalPurchase', 300000)
        ->assertJsonPath('data.totalPurchaseReturn', 20000)
        ->assertJsonPath('data.totalIncome', 100000)
        ->assertJsonPath('data.totalExpense', 40000)
        ->assertJsonPath('data.invoiceDue', 75000)
        ->assertJsonPath('data.totalCustomers', 1)
        ->assertJsonPath('data.totalSuppliers', 1)
        ->assertJsonPath('data.lowStock', 0)
        ->assertJsonCount(12, 'data.monthlyTrend')
        ->assertJsonPath('data.monthlyTrend.'.(now()->month - 1).'.sales', 1000000)
        ->assertJsonPath('data.monthlyTrend.'.(now()->month - 1).'.purchases', 300000)
        ->assertJsonStructure([
            'data' => [
                'recentOrders' => [
                    ['no', 'customer', 'status', 'total', 'date'],
                ],
                'topProducts' => [
                    ['name', 'qty'],
                ],
            ],
        ]);
});

it('serves admin, sales and analytics dashboard stats', function () {
    $token = adminToken(adminWithPermissions('reports.view'));

    $this->withToken($token)->getJson('/api/v1/admin/dashboard/admin')
        ->assertOk()
        ->assertJsonStructure(['data' => ['welcomeName', 'ordersToday', 'primaryMetrics', 'overview', 'chartSeries', 'salesStatics', 'categoryTotals']]);

    $this->withToken($token)->getJson('/api/v1/admin/dashboard/sales')
        ->assertOk()
        ->assertJsonStructure(['data' => ['bestSellers', 'recentTransactions', 'countryMarkers']]);

    $this->withToken($token)->getJson('/api/v1/admin/dashboard/analytics')
        ->assertOk()
        ->assertJsonStructure(['data' => ['kpis', 'conversion', 'performance', 'browsers', 'regions', 'topPages']]);
});
