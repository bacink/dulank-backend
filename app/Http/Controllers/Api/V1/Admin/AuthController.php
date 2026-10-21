<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Store\UserResource;
use App\Models\User;
use App\Models\UserAdmin;
use App\Services\TotpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request, TotpService $totp): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password) || ! $user->isAdmin() || $user->status !== 'Active' || UserAdmin::query()->where('user_id', $user->id)->where('status', '!=', 'Active')->exists()) {
            return ApiResponse::error('Kredensial admin tidak valid.', 401);
        }

        if ($user->hasEnabledTwoFactor() && ! $totp->verify($user->two_factor_secret, (string) $request->input('code', ''))) {
            return ApiResponse::error('Masukkan kode autentikasi yang valid.', 422, ['code' => ['Kode autentikasi diperlukan atau tidak valid.']]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken(
            $request->input('device_name') ?? 'admin',
            [AccountType::Admin->ability()],
        )->plainTextToken;

        activity('account-security')->causedBy($user)->log('Login admin berhasil');

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(['revoked' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => new UserResource($user),
            'roles' => $user?->getRoleNames() ?? [],
            'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
        ]);
    }
}
