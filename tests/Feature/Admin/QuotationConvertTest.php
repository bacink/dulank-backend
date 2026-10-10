<?php

use App\Models\Invoice;
use App\Models\Quotation;

it('creates an admin quotation from its document and converts it to an order', function () {
    $token = adminToken(adminWithPermissions('sales.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/quotations', [
        'customer' => 'PT. Maju Jaya',
        'email' => 'info@majujaya.com',
        'telp' => '+6281234567890',
        'channel' => 'Sales Staff',
        'status' => 'Send',
        'document' => [
            'items' => [
                ['productName' => 'Brosur PPDN', 'description' => 'A4 full color', 'order' => 100, 'unitPrice' => 50000],
            ],
            'shippingCost' => 25000,
            'taxRate' => 11,
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.customer', 'PT. Maju Jaya')
        ->assertJsonPath('data.channel', 'Sales Staff')
        ->assertJsonPath('data.status', 'Send')
        ->assertJsonPath('data.total', 5000000 + 550000 + 25000)
        ->assertJsonPath('data.items.0.productName', 'Brosur PPDN');

    $noQuotation = $created->json('data.noQuotation');

    expect($noQuotation)->toStartWith('QT-');

    $converted = $this->withToken($token)->postJson("/api/v1/admin/quotations/{$noQuotation}/order");

    $converted->assertCreated()
        ->assertJsonPath('data.salesChannel', 'Quotation')
        ->assertJsonPath('data.totalAmount', 5575000)
        ->assertJsonPath('data.items.0.productName', 'Brosur PPDN');

    $quotation = Quotation::query()->where('no_quotation', $noQuotation)->firstOrFail();

    expect($quotation->status)->toBe('Ordered')
        ->and($quotation->order_id)->not->toBeNull()
        ->and($quotation->order->source_quotation_id)->toBe($quotation->id)
        ->and(Invoice::query()->where('order_id', $quotation->order_id)->exists())->toBeTrue();

    // Konversi kedua ditolak.
    $this->withToken($token)->postJson("/api/v1/admin/quotations/{$noQuotation}/order")->assertStatus(409);

    // Update setelah konversi ditolak.
    $this->withToken($token)->putJson("/api/v1/admin/quotations/{$noQuotation}", [
        'customer' => 'PT. Maju Jaya',
    ])->assertStatus(422);
});
