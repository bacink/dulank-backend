<?php

use App\Models\User;

it('registers a store user and returns a store token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.test',
        'phone' => '+628123456789',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'attachTaxInvoice']]]);

    $this->assertDatabaseHas('users', ['email' => 'budi@example.test', 'type' => 'store']);
    $this->assertDatabaseHas('customers', ['user_id' => $response->json('data.user.id')]);
});

it('logs in a store user and returns token plus user resource', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email']]]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials with envelope error', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Kredensial tidak valid.');
});

it('rejects admin account at the store login endpoint', function () {
    $admin = User::factory()->admin()->create(['password' => 'secret123']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $admin->email,
        'password' => 'secret123',
    ])->assertStatus(401);
});

it('returns the authenticated user profile and revokes token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['store'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'avatar', 'joinDate']]);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('blocks unauthenticated access to user scoped endpoints', function () {
    $this->getJson('/api/v1/cart')->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('prevents a store token from accessing admin endpoints', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['store'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertStatus(403);
});

it('prevents an admin token from accessing store endpoints', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/cart')->assertStatus(403);
});

it('serves the compatibility alias /api/auth/me', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['store'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
