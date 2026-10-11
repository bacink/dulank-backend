<?php

use App\Models\BanIp;
use App\Models\DeliveryNote;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payslip;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('exports a report as xlsx', function () {
    Order::factory()->create(['number' => 'SO-XLSX-1', 'ordered_at' => now()]);

    $response = $this->withToken(adminToken(adminWithPermissions('reports.view')))
        ->get('/api/v1/admin/reports/sales/export?format=xlsx');

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('renders invoice, quotation, delivery note, purchase and payslip pdfs', function () {
    $order = Order::factory()->create(['number' => 'SO-PDF-1', 'contact' => ['name' => 'PT. Maju Jaya']]);
    OrderItem::factory()->for($order)->create(['name' => 'Brosur', 'quantity' => 10, 'price' => 5000, 'total' => 50000]);
    Invoice::factory()->for($order)->create(['invoice_no' => 'INV-PDF-1', 'amount' => 50000, 'amount_due' => 50000]);

    $quotation = Quotation::factory()->create(['no_quotation' => 'QT-PDF-1']);
    $quotation->items()->create(['product_name' => 'Nota', 'order_qty' => 5, 'unit_price' => 1000, 'amount' => 5000]);

    $deliveryNote = DeliveryNote::query()->create([
        'dn_no' => 'DN-PDF-1',
        'date' => now()->toDateString(),
        'customer' => 'PT. Maju Jaya',
        'status' => 'Pending',
    ]);
    $deliveryNote->items()->create(['description' => 'Brosur', 'qty' => 10, 'unit' => 'rim']);

    $purchase = Purchase::query()->create([
        'no_purchase' => 'PR-PDF-1',
        'date' => now()->toDateString(),
        'status' => 'Ordered',
        'amount' => 100000,
        'due' => 100000,
    ]);
    $purchase->lines()->create(['name' => 'Kertas', 'qty' => 2, 'unit' => 'rim', 'price' => 50000, 'total' => 100000]);

    $employee = Employee::query()->create(['employee_code' => 'EMP-PDF-1', 'name' => 'Budi', 'status' => 'Active']);
    $payslip = Payslip::query()->create([
        'slip_no' => 'SLP-PDF-1',
        'employee_id' => $employee->id,
        'period' => '2026-10',
        'salary_rate' => 1000000,
        'total' => 1000000,
        'status' => 'Unpaid',
    ]);
    $payslip->items()->create(['label' => 'Basic Salary', 'qty' => 1, 'rate' => 1000000, 'amount' => 1000000]);

    $finance = adminToken(adminWithPermissions('finance.manage'));
    $documents = adminToken(adminWithPermissions('sales.manage', 'orders.manage', 'purchases.manage'));
    $hrm = adminToken(adminWithPermissions('hrm.manage'));

    $this->withToken($finance)->get('/api/v1/admin/invoices/INV-PDF-1/pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->withToken($hrm)->get('/api/v1/admin/payslips/SLP-PDF-1/pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->withToken($documents)->get('/api/v1/admin/quotations/QT-PDF-1/pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->withToken($documents)->get('/api/v1/admin/delivery-notes/DN-PDF-1/pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->withToken($documents)->get('/api/v1/admin/purchases/PR-PDF-1/pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('blocks requests from banned ip addresses', function () {
    BanIp::query()->create([
        'ip_address' => '127.0.0.1',
        'reason' => 'Uji blokir',
        'status' => 'Active',
    ]);
    Cache::forget('ban_ips');

    $this->withToken(adminToken(adminWithPermissions('reports.view')))
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    // Endpoint publik store juga terblokir.
    $this->getJson('/api/v1/products')->assertStatus(403);
});

it('allows requests after the ban is cleared', function () {
    $ban = BanIp::query()->create(['ip_address' => '127.0.0.1', 'status' => 'Active']);
    Cache::forget('ban_ips');

    $this->getJson('/api/v1/products')->assertStatus(403);

    $ban->delete();
    Cache::forget('ban_ips');

    $this->getJson('/api/v1/products')->assertOk();
});

it('blocks with a banned ip even for authenticated users', function () {
    BanIp::query()->create(['ip_address' => '127.0.0.1', 'status' => 'Active']);
    Cache::forget('ban_ips');

    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(403);
});

it('returns a 401 json envelope for guests without an accept header', function () {
    // Regresi: default Laravel mengarahkan guest ke route('login') yang tidak ada
    // sehingga request tanpa Accept: application/json dibalas 500, bukan 401.
    $this->get('/api/v1/admin/orders')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});
