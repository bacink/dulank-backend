<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('submits a payment proof for an invoice and records the order timeline', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-101', 'due' => 111000]);
    $invoice = Invoice::factory()->for($order)->for($user)->create([
        'invoice_no' => 'INV-101',
        'amount' => 111000,
        'paid' => 0,
        'amount_due' => 111000,
    ]);

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->post('/api/v1/payments', [
            'payableType' => 'invoice',
            'payableId' => 'INV-101',
            'amount' => 111000,
            'method' => 'Transfer',
            'notes' => 'Transfer BCA',
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.amount', 111000)
        ->assertJsonPath('data.payableType', 'invoice')
        ->assertJsonPath('data.payableId', 'INV-101')
        ->assertJsonStructure(['data' => ['id', 'refNo', 'date', 'amount', 'method', 'status', 'proofUrl']]);

    $payment = Payment::query()->firstOrFail();

    expect($payment->proof_path)->not->toBeNull()
        ->and($payment->user_id)->toBe($user->id);
    Storage::disk('public')->assertExists($payment->proof_path);

    // Invoice belum berubah sampai admin verifikasi (Fase 3).
    expect($invoice->fresh()->paid)->toBe(0)
        ->and($invoice->fresh()->status->value)->toBe('Unpaid');

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => 'payment-submitted',
    ]);
});

it('rejects a payment amount over the remaining due', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['number' => 'SO-102']);
    Invoice::factory()->for($order)->for($user)->create([
        'invoice_no' => 'INV-102',
        'amount' => 50000,
        'amount_due' => 50000,
    ]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->post('/api/v1/payments', [
            'payableType' => 'invoice',
            'payableId' => 'INV-102',
            'amount' => 60000,
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['amount']]);
});

it('scopes invoices and payments to their owner', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->for($owner)->create();
    Invoice::factory()->for($order)->for($owner)->create(['invoice_no' => 'INV-303']);

    $this->withToken($other->createToken('t', ['store'])->plainTextToken)
        ->post('/api/v1/payments', [
            'payableType' => 'invoice',
            'payableId' => 'INV-303',
            'amount' => 1000,
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])
        ->assertStatus(404);
});

it('lists only the authenticated users payments', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    Invoice::factory()->for($order)->for($user)->create(['invoice_no' => 'INV-404']);

    $payment = Payment::query()->create([
        'payable_type' => Invoice::class,
        'payable_id' => Invoice::query()->where('invoice_no', 'INV-404')->value('id'),
        'direction' => 'in',
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'amount' => 1000,
        'method' => 'Transfer',
        'status' => 'Pending',
    ]);

    $otherOrder = Order::factory()->for($other)->create();
    Payment::query()->create([
        'payable_type' => Order::class,
        'payable_id' => $otherOrder->id,
        'direction' => 'in',
        'user_id' => $other->id,
        'date' => now()->toDateString(),
        'amount' => 1000,
    ]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/payments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $payment->id);
});
