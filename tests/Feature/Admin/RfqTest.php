<?php

use App\Models\RequestQuotation;

it('manages request quotations including duplication', function () {
    $token = adminToken(adminWithPermissions('sales.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/request-quotations', [
        'customer' => 'Toko Sumber Rejeki',
        'email' => 'sumber@example.test',
        'telp' => '+62811',
        'status' => 'Pending',
        'dueDate' => now()->addWeek()->toDateString(),
        'paymentTerm' => 'COD',
        'document' => [
            'items' => [['description' => 'HVS A4', 'quantity' => 10, 'unit' => 'rim', 'eta' => '3 hari']],
            'requestedTo' => [['department' => 'Purchasing', 'attn' => 'Budi', 'email' => 'budi@example.test', 'telp' => '+62812']],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.customer', 'Toko Sumber Rejeki')
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonStructure(['data' => ['id', 'noRequest', 'customer', 'email', 'telp', 'date', 'status', 'document']]);

    $noRequest = $created->json('data.noRequest');

    expect($noRequest)->toStartWith('RFQ-');

    $duplicated = $this->withToken($token)->postJson("/api/v1/admin/request-quotations/{$noRequest}/duplicate");

    $duplicated->assertCreated()
        ->assertJsonPath('data.customer', 'Toko Sumber Rejeki')
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.document.requestedTo.0.attn', 'Budi');

    $newNoRequest = $duplicated->json('data.noRequest');

    expect($newNoRequest)->not->toBe($noRequest);
    expect(RequestQuotation::query()->count())->toBe(2);

    // Dokumen sumber tidak berubah.
    expect(RequestQuotation::query()->where('no_request', $noRequest)->value('status'))->toBe('Pending');

    $this->withToken($token)->putJson("/api/v1/admin/request-quotations/{$noRequest}", [
        'customer' => 'Toko Sumber Rejeki',
        'status' => 'Complete',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Complete');

    $this->withToken($token)->getJson('/api/v1/admin/request-quotations?status=Complete')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->deleteJson("/api/v1/admin/request-quotations/{$newNoRequest}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);
});
