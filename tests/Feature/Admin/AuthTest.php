<?php

use App\Models\User;

it('logs in an admin and returns roles', function () {
    $admin = User::factory()->admin()->create(['password' => 'admin12345']);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => $admin->email,
        'password' => 'admin12345',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email'], 'roles']]);
});

it('rejects store accounts at the admin login endpoint', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertStatus(401)
        ->assertJsonPath('message', 'Kredensial admin tidak valid.');
});

it('returns admin identity for a valid admin token', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $admin->id)
        ->assertJsonStructure(['data' => ['user', 'roles', 'permissions']]);
});
