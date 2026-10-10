<?php

use App\Models\Product;
use App\Models\User;

it('adds a product to the wishlist and returns the existing entry for duplicates', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $created = $this->withToken($token)->postJson('/api/v1/wishlist', [
        'productId' => $product->id,
        'spec' => 'A4',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.productId', $product->id)
        ->assertJsonPath('data.spec', 'A4')
        ->assertJsonStructure(['data' => ['id', 'userId', 'productId', 'name', 'image', 'price', 'spec', 'addedAt', 'expireDate', 'expired']]);

    $this->withToken($token)->postJson('/api/v1/wishlist', [
        'productId' => $product->id,
        'spec' => 'A4',
    ])->assertOk()
        ->assertJsonPath('data.id', $created->json('data.id'));

    $this->assertDatabaseCount('wishlists', 1);
});

it('creates separate entries for different specs and validates the product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/wishlist', ['productId' => $product->id, 'spec' => 'A4'])->assertCreated();
    $this->withToken($token)->postJson('/api/v1/wishlist', ['productId' => $product->id, 'spec' => 'A5'])->assertCreated();

    $this->assertDatabaseCount('wishlists', 2);

    $this->withToken($token)->postJson('/api/v1/wishlist', ['productId' => 999999])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['productId']]);
});
