<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

it('verifies a submitted payment and recalculates the invoice', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-201', 'total' => 100000, 'due' => 100000]);
    $invoice = Invoice::factory()->for($order)->for($user)->create([
        'invoice_no' => 'INV-201',
        'amount' => 100000,
        'paid' => 0,
        'amount_due' => 100000,
    ]);

    $payment = Payment::query()->create([
        'payable_type' => Invoice::class,
        'payable_id' => $invoice->id,
        'direction' => 'in',
        'ref_no' => 'PAY-00001',
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'amount' => 40000,
        'method' => 'Transfer',
        'status' => 'Pending',
    ]);

    $admin = adminWithPermissions('finance.manage');

    $this->withToken(adminToken($admin))
        ->postJson("/api/v1/admin/payments/{$payment->id}/verify")
        ->assertOk()
        ->assertJsonPath('data.status', 'Verified')
        ->assertJsonPath('data.type', 'Payment-In');

    expect($invoice->fresh()->paid)->toBe(40000)
        ->and($invoice->fresh()->amount_due)->toBe(60000)
        ->and($invoice->fresh()->status->value)->toBe('Partial');

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => 'payment-verified',
    ]);

    $this->assertDatabaseHas('activity_log', ['subject_type' => Payment::class, 'subject_id' => $payment->id]);
});

it('rejects a payment without recalculating the invoice', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($order)->for($user)->create(['invoice_no' => 'INV-202', 'amount' => 50000, 'amount_due' => 50000]);

    $payment = Payment::query()->create([
        'payable_type' => Invoice::class,
        'payable_id' => $invoice->id,
        'direction' => 'in',
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'amount' => 50000,
        'method' => 'Transfer',
        'status' => 'Pending',
    ]);

    $this->withToken(adminToken(adminWithPermissions('finance.manage')))
        ->postJson("/api/v1/admin/payments/{$payment->id}/reject", ['note' => 'Bukti tidak jelas'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Rejected');

    expect($invoice->fresh()->paid)->toBe(0)
        ->and($invoice->fresh()->status->value)->toBe('Unpaid');
});

it('records a payment directly and marks the invoice paid', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-203']);
    Invoice::factory()->for($order)->for($user)->create([
        'invoice_no' => 'INV-203',
        'amount' => 75000,
        'paid' => 0,
        'amount_due' => 75000,
    ]);

    $this->withToken(adminToken(adminWithPermissions('finance.manage')))
        ->postJson('/api/v1/admin/invoices/INV-203/payments', [
            'amount' => 75000,
            'method' => 'Transfer',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'Verified')
        ->assertJsonPath('data.payableId', 'INV-203');

    $invoice = Invoice::query()->where('invoice_no', 'INV-203')->firstOrFail();

    expect($invoice->paid)->toBe(75000)
        ->and($invoice->amount_due)->toBe(0)
        ->and($invoice->status->value)->toBe('Paid');
});

it('denies payment verification without the finance permission', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_no' => 'INV-204']);
    $payment = Payment::query()->create([
        'payable_type' => Invoice::class,
        'payable_id' => $invoice->id,
        'direction' => 'in',
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'amount' => 1000,
        'status' => 'Pending',
    ]);

    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->postJson("/api/v1/admin/payments/{$payment->id}/verify")
        ->assertStatus(403);

    $this->withToken(adminToken(adminWithPermissions('finance.manage')))
        ->getJson('/api/v1/admin/payments?status=Pending')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['page', 'limit', 'total']]);
});
