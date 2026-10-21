<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordRecoveryController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => mb_strtolower($validated['email']), 'type' => 'admin', 'status' => 'Active']);

        return ApiResponse::success(['requested' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $validated['email'] = mb_strtolower($validated['email']);
        $status = DB::transaction(fn () => Password::reset($validated + ['type' => 'admin', 'status' => 'Active'], function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'password_changed_at' => now(), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        }));
        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('Tautan pemulihan tidak valid atau sudah kedaluwarsa.', 422, ['token' => ['Tautan pemulihan tidak valid atau sudah kedaluwarsa.']]);
        }

        return ApiResponse::success(['updated' => true]);
    }
}
