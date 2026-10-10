<?php

use App\Models\Address;
use App\Models\User;

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'recipientName' => 'Ahmad Dhani',
        'phone' => '+62852053769',
        'type' => 'Office',
        'name' => 'Toko Kelontong',
        'street' => 'Jl. Arif Rahman Hakim',
        'district' => 'TELUK JAMBE TIMUR',
        'city' => 'KAB. KARAWANG',
        'province' => 'JAWA BARAT',
        'postalCode' => '41360',
        'country' => 'ID',
    ], $overrides);
}

it('creates, updates and deletes user addresses with default handling', function () {
    $user = User::factory()->create();
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $first = $this->withToken($token)->postJson('/api/v1/addresses', addressPayload());
    $first->assertCreated()
        ->assertJsonPath('data.isDefault', true)
        ->assertJsonStructure(['data' => ['id', 'userId', 'recipientName', 'phone', 'type', 'name', 'street', 'district', 'city', 'province', 'postalCode', 'country']]);

    $second = $this->withToken($token)->postJson('/api/v1/addresses', addressPayload([
        'recipientName' => 'Budi',
        'isDefault' => true,
    ]));
    $second->assertCreated()->assertJsonPath('data.isDefault', true);

    expect(Address::query()->find($first->json('data.id'))->is_default)->toBeFalse()
        ->and(Address::query()->where('is_default', true)->count())->toBe(1);

    $addressId = $second->json('data.id');

    $this->withToken($token)->putJson('/api/v1/addresses/'.$addressId, addressPayload([
        'recipientName' => 'Budi Updated',
        'isDefault' => true,
    ]))->assertOk()->assertJsonPath('data.recipientName', 'Budi Updated');

    $this->withToken($token)->deleteJson('/api/v1/addresses/'.$addressId)
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    // Address default dipromosikan ke alamat tersisa.
    expect($user->addresses()->count())->toBe(1)
        ->and($user->addresses()->first()->is_default)->toBeTrue();

    $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
});

it('scopes address mutations to their owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $address = $other->addresses()->create([
        'recipient_name' => 'Orang Lain',
        'phone' => '+62',
        'type' => 'Home',
        'name' => 'Rumah',
        'street' => 'Jl. X',
        'city' => 'Karawang',
        'country' => 'ID',
    ]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->putJson('/api/v1/addresses/'.$address->id, addressPayload())
        ->assertStatus(404);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->deleteJson('/api/v1/addresses/'.$address->id)
        ->assertStatus(404);
});
