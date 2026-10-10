<?php

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

function storeToken(User $user): string
{
    return $user->createToken('test', ['store'])->plainTextToken;
}

it('adds items to the cart with snapshot fields and merges identical specs', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Yasin Tahlil', 'price_from' => 65000]);

    $payload = ['productId' => $product->id, 'quantity' => 2, 'price' => 65000, 'spec' => 'A4, Art Paper'];

    $response = $this->withToken(storeToken($user))->postJson('/api/v1/cart', $payload);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.productId', $product->id)
        ->assertJsonPath('data.0.quantity', 2)
        ->assertJsonPath('data.0.name', 'Yasin Tahlil')
        ->assertJsonStructure(['data' => [['id', 'productId', 'name', 'image', 'quantity', 'price', 'spec']]]);

    expect($response->json('data.0.id'))->toBeString();

    $this->withToken(storeToken($user))->postJson('/api/v1/cart', $payload)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 4);
});

it('updates, deletes and clears cart items', function () {
    $user = User::factory()->create();
    $item = CartItem::factory()->for($user)->create(['quantity' => 1]);

    $this->withToken(storeToken($user))->putJson('/api/v1/cart/'.$item->id, ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('data.quantity', 5);

    $this->withToken(storeToken($user))->deleteJson('/api/v1/cart/'.$item->id)
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);

    CartItem::factory()->count(2)->for($user)->create();

    $this->withToken(storeToken($user))->postJson('/api/v1/cart/clear')
        ->assertOk()
        ->assertJsonPath('data.cleared', true);

    $this->assertDatabaseCount('cart_items', 0);
});

it('does not expose other users cart items', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    CartItem::factory()->for($other)->create();

    $this->withToken(storeToken($user))->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects invalid cart payloads', function () {
    $user = User::factory()->create();

    $this->withToken(storeToken($user))->postJson('/api/v1/cart', ['productId' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['productId']]);
});
