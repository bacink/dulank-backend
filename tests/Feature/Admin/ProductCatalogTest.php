<?php

use App\Models\Product;
use App\Models\User;

it('manages products with variants and images', function () {
    $token = adminToken(adminWithPermissions('products.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/products', [
        'name' => 'Brosur Full Color A4',
        'category' => 'Promotion',
        'subCategory' => 'Brosur',
        'unit' => 'Rim',
        'price' => 50000,
        'priceType' => 'Range',
        'quantity' => 100,
        'minOrderQty' => 10,
        'description' => 'Cetak brosur full color',
        'images' => ['/images/brosur.jpg'],
        'variants' => [
            ['variation' => 'Ukuran', 'value' => 'A4', 'price' => 50000, 'quantity' => 50],
            ['variation' => 'Ukuran', 'value' => 'A5', 'price' => 35000, 'quantity' => 50],
        ],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Brosur Full Color A4')
        ->assertJsonPath('data.category', 'Promotion')
        ->assertJsonPath('data.subCategory', 'Brosur')
        ->assertJsonPath('data.unit', 'Rim')
        ->assertJsonPath('data.priceType', 'Range')
        ->assertJsonPath('data.variants.0.value', 'A4')
        ->assertJsonPath('data.images.0', '/images/brosur.jpg')
        ->assertJsonStructure(['data' => ['id', 'code', 'slug', 'categoryId', 'unitId', 'price', 'quantity', 'status', 'images', 'variants']]);

    expect($created->json('data.code'))->toStartWith('PRD-');

    $productId = $created->json('data.id');

    // Price range terhitung dari varian.
    $product = Product::query()->findOrFail((int) $productId);
    expect($product->price_from)->toBe(35000)
        ->and($product->price_to)->toBe(50000);

    $this->withToken($token)->getJson('/api/v1/admin/products?search=Brosur')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->putJson("/api/v1/admin/products/{$productId}", [
        'name' => 'Brosur Full Color A4 (Revisi)',
        'category' => 'Promotion',
        'price' => 55000,
        'status' => 'Inactive',
        'variants' => [
            ['variation' => 'Ukuran', 'value' => 'A4', 'price' => 55000, 'quantity' => 100],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Brosur Full Color A4 (Revisi)')
        ->assertJsonPath('data.status', 'Inactive')
        ->assertJsonCount(1, 'data.variants');

    $this->withToken($token)->deleteJson("/api/v1/admin/products/{$productId}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);
});

it('manages catalog masters: categories, sub-categories, units, variants, processes, workshop services', function () {
    $token = adminToken(adminWithPermissions('products.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/categories', ['name' => 'Packaging', 'code' => 'PKG'])
        ->assertCreated()->assertJsonPath('data.code', 'PKG');

    $this->withToken($token)->postJson('/api/v1/admin/sub-categories', ['category' => 'Packaging', 'name' => 'Box'])
        ->assertCreated()->assertJsonPath('data.name', 'Box');

    $this->withToken($token)->postJson('/api/v1/admin/units', ['name' => 'Rim', 'shortName' => 'rm'])
        ->assertCreated();

    $this->withToken($token)->postJson('/api/v1/admin/variants', ['name' => 'Ukuran', 'values' => 'A4, A5, A3'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ukuran');

    $this->withToken($token)->getJson('/api/v1/admin/variants')
        ->assertOk()
        ->assertJsonPath('data.0.values', 'A4, A5, A3');

    $product = Product::factory()->create();

    $this->withToken($token)->postJson('/api/v1/admin/product-processes', [
        'productId' => $product->id,
        'processName' => 'Laminasi Doff',
        'code' => 'PP-001',
    ])
        ->assertCreated()
        ->assertJsonPath('data.processName', 'Laminasi Doff');

    $this->withToken($token)->postJson('/api/v1/admin/workshop-services', [
        'category' => 'printing',
        'name' => 'Cetak Offset A3',
        'printingType' => 'Offset',
        'maxHeight' => 42,
        'maxWidth' => 29.7,
        'sizeUnit' => 'cm',
        'price' => 150000,
        'priceUnit' => 'pcs',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Cetak Offset A3')
        ->assertJsonPath('data.printingType', 'Offset');

    $this->withToken($token)->getJson('/api/v1/admin/sub-categories')
        ->assertOk()
        ->assertJsonPath('data.0.category', 'Packaging');
});

it('requires products permission', function () {
    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/products')
        ->assertStatus(403);
});
