<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\TotpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class AccountSecurityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'passwordLastChanged' => $user->password_changed_at?->toIso8601String() ?? '',
            'twoFactor' => $user->hasEnabledTwoFactor(), 'googleAuth' => $user->hasEnabledTwoFactor(),
            'phone' => $user->phone ?? '', 'email' => $user->email,
            'devices' => $user->tokens()->orderByDesc('id')->get()->map(fn ($token) => [
                'id' => (string) $token->id, 'name' => $token->name, 'ip' => '',
                'lastActive' => ($token->last_used_at ?? $token->created_at)?->toIso8601String(),
            ])->values(), 'activities' => Activity::query()->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id)->where('log_name', 'account-security')->orderByDesc('id')->limit(30)->get()->map(fn ($activity) => ['id' => (string) $activity->id, 'action' => $activity->description, 'device' => '', 'date' => $activity->created_at?->toIso8601String()])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'twoFactor' => ['prohibited'], 'googleAuth' => ['prohibited'], 'passwordLastChanged' => ['prohibited'],
        ]);
        $request->user()->forceFill($validated)->save();

        activity('account-security')->causedBy($request->user())->log('Pengaturan keamanan akun diperbarui');

        return $this->show($request);
    }

    public function setup(Request $request, TotpService $totp): JsonResponse
    {
        $this->verifyPassword($request);
        $user = $request->user();
        abort_if($user->hasEnabledTwoFactor(), 422, 'Autentikasi dua faktor sudah aktif.');
        $secret = $totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();

        return ApiResponse::success(['secret' => $secret, 'otpauthUrl' => $totp->otpauthUrl($user->email, $secret)]);
    }

    public function confirm(Request $request, TotpService $totp): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();
        abort_unless($user->two_factor_secret && $totp->verify($user->two_factor_secret, $request->input('code')), 422, 'Kode autentikasi tidak valid.');
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        activity('account-security')->causedBy($request->user())->log('Pengaturan keamanan akun diperbarui');

        return $this->show($request);
    }

    public function disable(Request $request): JsonResponse
    {
        $this->verifyPassword($request);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        activity('account-security')->causedBy($request->user())->log('Pengaturan keamanan akun diperbarui');

        return $this->show($request);
    }

    public function revoke(Request $request, string $id): JsonResponse
    {
        abort_if((string) $request->user()->currentAccessToken()?->getKey() === $id, 422, 'Gunakan logout untuk mengakhiri sesi saat ini.');
        $request->user()->tokens()->whereKey($id)->firstOrFail()->delete();

        return ApiResponse::success(['revoked' => true]);
    }

    private function verifyPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']]);
        abort_unless(Hash::check($request->input('password'), $request->user()->password), 422, 'Password saat ini salah.');
    }
}
