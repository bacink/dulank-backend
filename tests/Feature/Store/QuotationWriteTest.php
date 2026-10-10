<?php

use App\Models\Quotation;
use App\Models\User;

it('creates a quotation with server computed tax and customer snapshot', function () {
    $user = User::factory()->create(['name' => 'Ahmad Dhani', 'email' => 'ahmad@example.test']);

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->postJson('/api/v1/quotations', [
            'items' => [
                ['name' => 'Brosur PPDN', 'quantity' => 500, 'unitPrice' => 45000, 'spec' => 'A4'],
            ],
            'customerInfo' => ['name' => 'PT. Maju Jaya', 'email' => 'info@majujaya.com', 'phone' => '+628123'],
            'shippingCost' => 25000,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.customerInfo.name', 'PT. Maju Jaya')
        ->assertJsonPath('data.items.0.total', 22500000)
        ->assertJsonPath('data.subtotal', 22500000)
        ->assertJsonPath('data.tax', 2475000) // 11%
        ->assertJsonPath('data.shippingCost', 25000)
        ->assertJsonPath('data.total', 25000000) // 22500000 + 2475000 + 25000
        ->assertJsonStructure(['data' => ['id', 'userId', 'date', 'status', 'items', 'customerInfo', 'total']]);

    $quotation = Quotation::query()->firstOrFail();

    expect($quotation->no_quotation)->toStartWith('QT-')
        ->and($quotation->channel->value)->toBe('online');
});

it('updates a draft quotation and replaces the items', function () {
    $user = User::factory()->create();
    $quotation = Quotation::factory()->for($user)->create(['status' => 'Draft']);
    $quotation->items()->create(['product_name' => 'Lama', 'order_qty' => 1, 'unit_price' => 1000, 'amount' => 1000]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->putJson('/api/v1/quotations/'.$quotation->no_quotation, [
            'items' => [
                ['name' => 'Baru', 'quantity' => 2, 'unitPrice' => 50000],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.items.0.name', 'Baru')
        ->assertJsonPath('data.subtotal', 100000)
        ->assertJsonPath('data.total', 111000);

    expect($quotation->items()->count())->toBe(1)
        ->and($quotation->items()->first()->product_name)->toBe('Baru');
});

it('blocks updates for other users and non editable statuses', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $quotation = Quotation::factory()->for($other)->create(['status' => 'Pending']);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->putJson('/api/v1/quotations/'.$quotation->no_quotation, [
            'items' => [['name' => 'X', 'quantity' => 1, 'unitPrice' => 1000]],
        ])
        ->assertStatus(403);

    $ordered = Quotation::factory()->for($user)->create(['status' => 'Ordered']);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->putJson('/api/v1/quotations/'.$ordered->no_quotation, [
            'items' => [['name' => 'X', 'quantity' => 1, 'unitPrice' => 1000]],
        ])
        ->assertStatus(422);
});

it('validates quotation items', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->postJson('/api/v1/quotations', ['items' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['items']]);
});
