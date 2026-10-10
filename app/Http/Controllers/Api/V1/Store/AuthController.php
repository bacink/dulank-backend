<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\AccountType;
use App\Enums\CustomerChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Store\UserResource;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'phone' => $request->input('phone'),
            'password' => $request->string('password')->toString(),
            'type' => AccountType::Store,
            'join_date' => now(),
            'status' => 'Active',
        ]);

        Customer::query()->create([
            'user_id' => $user->id,
            'code' => 'CUS-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'channel' => CustomerChannel::Online,
            'date_join' => now()->toDateString(),
        ]);

        $token = $user->createToken(
            $request->input('device_name') ?? 'store',
            [AccountType::Store->ability()],
        )->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], [], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password) || ! $user->isStore()) {
            return ApiResponse::error('Kredensial tidak valid.', 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken(
            $request->input('device_name') ?? 'store',
            [AccountType::Store->ability()],
        )->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(['revoked' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
