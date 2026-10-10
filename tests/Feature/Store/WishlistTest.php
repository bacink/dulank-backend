<?php

use App\Models\User;
use App\Models\Wishlist;

it('lists only the authenticated users wishlist entries', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Wishlist::factory()->for($user)->create(['name' => 'Brosur']);
    Wishlist::factory()->for($other)->create();

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/wishlist');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Brosur')
        ->assertJsonStructure(['data' => [['id', 'userId', 'productId', 'name', 'image', 'price', 'spec', 'addedAt', 'expireDate', 'expired']]]);
});

it('really deletes a wishlist item (stub fixed)', function () {
    $user = User::factory()->create();
    $item = Wishlist::factory()->for($user)->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->deleteJson('/api/v1/wishlist/'.$item->id)
        ->assertOk()
        ->assertJsonPath('data.removed', true)
        ->assertJsonPath('data.id', (string) $item->id);

    $this->assertDatabaseMissing('wishlists', ['id' => $item->id]);
});

it('returns 404 when deleting another users wishlist item', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $item = Wishlist::factory()->for($other)->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->deleteJson('/api/v1/wishlist/'.$item->id)
        ->assertStatus(404);

    $this->assertDatabaseHas('wishlists', ['id' => $item->id]);
});
