<?php

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Support\Facades\Hash;

it('shows the profile with addresses', function () {
    $user = User::factory()->create(['name' => 'Ahmad Dhani']);
    $user->addresses()->create([
        'recipient_name' => 'Ahmad',
        'phone' => '+62',
        'type' => 'Office',
        'name' => 'Kantor',
        'street' => 'Jl. X',
        'city' => 'Karawang',
    ]);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Ahmad Dhani')
        ->assertJsonPath('data.user.twoFactorEnabled', false)
        ->assertJsonCount(1, 'data.addresses');
});

it('updates profile fields', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->putJson('/api/v1/profile', [
            'name' => 'Nama Baru',
            'companyName' => 'PT Dulank Semesta Cida',
            'npwp' => '1234567890123456',
            'attachTaxInvoice' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru')
        ->assertJsonPath('data.companyName', 'PT Dulank Semesta Cida')
        ->assertJsonPath('data.attachTaxInvoice', true);

    expect($user->fresh()->company_name)->toBe('PT Dulank Semesta Cida');
});

it('changes the password, revokes other tokens and allows login with the new password', function () {
    $user = User::factory()->create(['password' => 'secret123']);
    $current = $user->createToken('current', ['store'])->plainTextToken;
    $other = $user->createToken('other', ['store'])->plainTextToken;

    $this->withToken($current)->putJson('/api/v1/profile/password', [
        'current_password' => 'secret123',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();

    // Token saat ini tetap hidup, token lain dicabut.
    $this->withToken($current)->getJson('/api/v1/auth/me')->assertOk();
    $this->withToken($other)->getJson('/api/v1/auth/me')->assertStatus(401);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'newpassword123',
    ])->assertOk();
});

it('rejects password change with a wrong current password', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'salah',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['current_password']]);
});

it('enables, confirms and disables two factor authentication', function () {
    $user = User::factory()->create(['password' => 'secret123']);
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $enable = $this->withToken($token)->putJson('/api/v1/profile/2fa', ['password' => 'secret123']);
    $enable->assertOk()
        ->assertJsonPath('data.confirmed', false)
        ->assertJsonStructure(['data' => ['secret', 'otpauthUrl']]);

    $secret = $enable->json('data.secret');
    $code = app(TotpService::class)->code($secret);

    $this->withToken($token)->postJson('/api/v1/profile/2fa/confirm', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.enabled', true);

    expect($user->fresh()->hasEnabledTwoFactor())->toBeTrue();

    $this->withToken($token)->deleteJson('/api/v1/profile/2fa', ['password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);

    expect($user->fresh()->hasEnabledTwoFactor())->toBeFalse();
});

it('rejects an invalid otp code during confirmation', function () {
    $user = User::factory()->create(['password' => 'secret123']);
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $this->withToken($token)->putJson('/api/v1/profile/2fa', ['password' => 'secret123'])->assertOk();

    $this->withToken($token)->postJson('/api/v1/profile/2fa/confirm', ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['code']]);
});
