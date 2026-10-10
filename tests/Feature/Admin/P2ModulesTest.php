<?php

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Expense;
use App\Models\FlowCategory;
use App\Models\FlowName;
use App\Models\Income;
use App\Models\JobBranch;
use App\Models\JobOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;

it('serves the extended report types', function () {
    $token = adminToken(adminWithPermissions('reports.view'));

    $order = Order::factory()->create(['total' => 1000000, 'sub_total' => 900000, 'tax' => 100000, 'ordered_at' => now()]);

    $supplier = Supplier::query()->create(['code' => 'SUP-RPT', 'name' => 'PT Kertas Jaya']);
    Purchase::query()->create([
        'no_purchase' => 'PR-RPT-1',
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'amount' => 500000,
        'paid' => 0,
        'due' => 500000,
        'status' => 'Ordered',
        'payment_status' => 'Unpaid',
    ]);

    $customer = Customer::query()->create(['code' => 'CUS-RPT', 'channel' => 'Online', 'date_join' => now()]);
    CustomerAccountEntry::query()->create(['customer_id' => $customer->id, 'source_type' => 'sale', 'amount' => 750000, 'occurred_at' => now()]);

    Income::query()->create(['date' => now()->toDateString(), 'no' => 'INC-RPT-1', 'name' => 'Penjualan', 'amount' => 300000, 'is_cancelled' => false]);
    Expense::query()->create(['no_expense' => 'EXP-RPT-1', 'date' => now()->toDateString(), 'name' => 'Listrik', 'amount' => 200000, 'paid' => 200000, 'due' => 0, 'status' => 'Paid']);

    $this->withToken($token)->getJson('/api/v1/admin/reports/annual')
        ->assertOk()
        ->assertJsonPath('data.year', now()->year)
        ->assertJsonStructure(['data' => ['year', 'months', 'totalOrders', 'totalSales']]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/best-seller')
        ->assertOk()
        ->assertJsonStructure(['data' => ['products']]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/customer-dues')
        ->assertOk()
        ->assertJsonPath('data.totalDue', 750000)
        ->assertJsonPath('data.customers.0.due', 750000);

    $this->withToken($token)->getJson('/api/v1/admin/reports/supplier-dues')
        ->assertOk()
        ->assertJsonPath('data.totalDue', 500000);

    $this->withToken($token)->getJson('/api/v1/admin/reports/suppliers')
        ->assertOk()
        ->assertJsonPath('data.suppliers.0.supplier', 'PT Kertas Jaya');

    $this->withToken($token)->getJson('/api/v1/admin/reports/taxes')
        ->assertOk()
        ->assertJsonPath('data.output.vat', 100000)
        ->assertJsonStructure(['data' => ['output' => ['dpp', 'vat', 'total', 'documents'], 'input' => ['dpp', 'vat', 'documents']]]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/profit-loss')
        ->assertOk()
        ->assertJsonPath('data.revenue', 1000000)
        ->assertJsonPath('data.expense', 200000)
        ->assertJsonStructure(['data' => ['revenue', 'cogs', 'grossProfit', 'expense', 'netProfit']]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/invoices')
        ->assertOk()
        ->assertJsonStructure(['data' => ['totalInvoices', 'totalAmount', 'totalPaid', 'totalDue', 'byStatus']]);

    $this->withToken($token)->getJson('/api/v1/admin/reports/expenses')
        ->assertOk()
        ->assertJsonPath('data.totalPaid', 200000);

    $this->withToken($token)->getJson('/api/v1/admin/reports/incomes')
        ->assertOk()
        ->assertJsonPath('data.totalAmount', 300000);
});

it('manages tax rates', function () {
    $token = adminToken(adminWithPermissions('finance.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/tax-rates', [
        'name' => 'PPN 12%',
        'rate' => 12,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'PPN 12%')
        ->assertJsonPath('data.rate', 12);

    $id = $created->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/tax-rates/{$id}", ['name' => 'PPN 11%', 'rate' => 11])
        ->assertOk()->assertJsonPath('data.rate', 11);

    $this->withToken($token)->getJson('/api/v1/admin/tax-rates')
        ->assertOk()
        ->assertJsonFragment(['name' => 'PPN 11%']);

    expect(TaxRate::query()->count())->toBeGreaterThan(0);
});

it('serves POS catalog and held orders', function () {
    $token = adminToken(adminWithPermissions('sales.manage'));

    Product::factory()->create(['name' => 'Kartu Nama', 'price_from' => 35000]);

    $this->withToken($token)->getJson('/api/v1/admin/pos/products?search=Kartu')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.price', 35000);

    $held = $this->withToken($token)->postJson('/api/v1/admin/pos/held-orders', [
        'customer' => 'Walk-in',
        'items' => [
            ['name' => 'Kartu Nama', 'quantity' => 2, 'price' => 35000],
        ],
    ]);

    $held->assertCreated()->assertJsonPath('data.total', 77700); // 70.000 + PPN 11%

    $this->withToken($token)->getJson('/api/v1/admin/pos/held-orders')
        ->assertOk()
        ->assertJsonPath('data.0.customer', 'Walk-in')
        ->assertJsonPath('data.0.itemCount', 1);

    $id = $held->json('data.id');

    $this->withToken($token)->deleteJson("/api/v1/admin/pos/held-orders/{$id}")
        ->assertOk()->assertJsonPath('data.removed', true);
});

it('serves job-list and my-jobs', function () {
    $token = adminToken(adminWithPermissions('workflow.manage'));

    $category = FlowCategory::query()->create(['name' => 'Cetak']);
    FlowName::query()->create(['flow_category_id' => $category->id, 'name' => 'Cetak Offset', 'incentive' => 500]);

    $order = JobOrder::query()->create(['no' => 'JO-LIST-1', 'job_title' => 'Brosur', 'status' => 'On Process']);
    JobBranch::query()->create([
        'no' => 'JB-LIST-1',
        'job_order_id' => $order->id,
        'job_title' => 'Brosur',
        'flow_name' => 'Cetak Offset',
        'customer' => 'PT Maju',
        'qty' => 100,
        'assignees' => ['Budi Santoso'],
        'status' => 'Waiting',
        'date' => now()->toDateString(),
    ]);

    $this->withToken($token)->getJson('/api/v1/admin/job-list')
        ->assertOk()
        ->assertJsonPath('data.jobs.0.no', 'JB-LIST-1')
        ->assertJsonPath('data.flowCategories.0.name', 'Cetak')
        ->assertJsonStructure(['data' => ['jobs', 'flowCategories']]);

    $this->withToken($token)->getJson('/api/v1/admin/my-jobs?assignee=Budi Santoso')
        ->assertOk()
        ->assertJsonPath('data.0.flowName', 'Cetak Offset');

    $this->withToken($token)->getJson('/api/v1/admin/my-jobs?assignee=Orang Lain')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('manages gateway settings and admin profile', function () {
    $token = adminToken(adminWithPermissions('settings.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/gateways/payment-gateways', [
        'name' => 'Midtrans',
        'code' => 'midtrans',
        'config' => ['mode' => 'sandbox'],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Midtrans')
        ->assertJsonPath('data.config.mode', 'sandbox');

    $id = $created->json('data.id');

    $this->withToken($token)->getJson('/api/v1/admin/gateways/payment-gateways')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Midtrans');

    $this->withToken($token)->putJson("/api/v1/admin/gateways/payment-gateways/{$id}", [
        'name' => 'Midtrans Production',
        'status' => 'Inactive',
    ])->assertOk()->assertJsonPath('data.name', 'Midtrans Production');

    $this->withToken($token)->deleteJson("/api/v1/admin/gateways/payment-gateways/{$id}")
        ->assertOk()->assertJsonPath('data.removed', true);

    // Profil admin (tanpa permission modul).
    $admin = User::factory()->admin()->create(['password' => 'secret123']);

    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/profile')
        ->assertOk()
        ->assertJsonPath('data.user.id', $admin->id);

    $this->withToken(adminToken($admin))->putJson('/api/v1/admin/profile', ['name' => 'Admin Updated'])
        ->assertOk()->assertJsonPath('data.name', 'Admin Updated');

    $this->withToken(adminToken($admin))->putJson('/api/v1/admin/profile/password', [
        'current_password' => 'secret123',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ])->assertOk();
});
