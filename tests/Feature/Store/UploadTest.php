<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads an avatar and persists the path', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->post('/api/v1/uploads/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['path', 'url']]);

    $path = $response->json('data.path');

    expect($user->fresh()->avatar_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('rejects a non image avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->post('/api/v1/uploads/avatar', [
            'avatar' => UploadedFile::fake()->create('malicious.php', 10, 'text/x-php'),
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['avatar']]);
});

it('uploads an artwork file', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->post('/api/v1/uploads/artwork', [
            'file' => UploadedFile::fake()->create('desain.pdf', 200, 'application/pdf'),
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'desain.pdf')
        ->assertJsonStructure(['data' => ['path', 'url', 'name', 'size']]);

    Storage::disk('public')->assertExists($response->json('data.path'));
});
