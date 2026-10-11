<?php

use App\Models\Machine;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\Partner;
use App\Models\User;

it('requires a store token for calculator CRUD', function () {
    $this->getJson('/api/v1/calculator/machines')->assertStatus(401);
    $this->postJson('/api/v1/calculator/machines')->assertStatus(401);
    $this->getJson('/api/v1/calculator/paper-prices')->assertStatus(401);
});

it('lists, creates, updates and deletes a partner machine', function () {
    $user = User::factory()->create(['type' => 'store']);
    $partner = Partner::factory()->create(['type' => 'printing_shop', 'name' => 'Percetakan Uji']);
    $token = $user->createToken('t', ['store'])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson('/api/v1/calculator/machines', [
        'merchant_id' => $partner->id,
        'kind' => 'printing',
        'name' => 'Heidelberg SM52',
        'size' => '52x72 cm',
        'price' => 250000,
        'unit_price' => 65,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Heidelberg SM52')
        ->assertJsonPath('data.source', 'Percetakan Uji');

    $id = Machine::where('name', 'Heidelberg SM52')->first()->id;

    $this->getJson('/api/v1/calculator/machines?merchant_id='.$partner->id, $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $id);

    $this->putJson('/api/v1/calculator/machines/'.$id, [
        'price' => 275000,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.price', 275000);

    $this->deleteJson('/api/v1/calculator/machines/'.$id, [], $headers)
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('machines', ['id' => $id]);
});

it('validates machine kind and required fields', function () {
    $user = User::factory()->create(['type' => 'store']);
    $partner = Partner::factory()->create();
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $this->postJson('/api/v1/calculator/machines', [
        'merchant_id' => $partner->id,
        'kind' => 'bogus',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kind', 'name']);
});

it('creates a paper group and its prices', function () {
    $user = User::factory()->create(['type' => 'store']);
    $partner = Partner::factory()->create(['type' => 'paper_store', 'name' => 'Toko Kertas Uji']);
    $token = $user->createToken('t', ['store'])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson('/api/v1/calculator/paper-groups', [
        'merchant_id' => $partner->id,
        'name' => 'Art Carton',
        'merk' => 'Ika',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Art Carton')
        ->assertJsonPath('data.brand', 'Ika');

    $group = PaperGroup::where('name', 'Art Carton')->first();

    $this->postJson('/api/v1/calculator/paper-prices', [
        'paper_group_id' => $group->id,
        'name' => 'Art Carton 260gr',
        'price' => 15000,
        'min_order' => 100,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.price', 15000)
        ->assertJsonPath('data.group', 'Art Carton');

    $this->getJson('/api/v1/calculator/paper-prices?merchant_id='.$partner->id, $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $price = PaperPrice::where('name', 'Art Carton 260gr')->first();
    $this->putJson('/api/v1/calculator/paper-prices/'.$price->id, [
        'price' => 16000,
    ], $headers)->assertOk()->assertJsonPath('data.price', 16000);

    $this->deleteJson('/api/v1/calculator/paper-prices/'.$price->id, [], $headers)
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->deleteJson('/api/v1/calculator/paper-groups/'.$group->id, [], $headers)
        ->assertOk();

    $this->assertDatabaseMissing('paper_prices', ['id' => $price->id]);
    $this->assertDatabaseMissing('paper_groups', ['id' => $group->id]);
});

it('returns 404 for unknown machine and paper price updates', function () {
    $user = User::factory()->create(['type' => 'store']);
    $token = $user->createToken('t', ['store'])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->putJson('/api/v1/calculator/machines/999999', ['name' => 'X'], $headers)->assertNotFound();
    $this->putJson('/api/v1/calculator/paper-prices/999999', ['price' => 1], $headers)->assertNotFound();
});