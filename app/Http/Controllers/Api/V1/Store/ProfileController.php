<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\ConfirmTwoFactorRequest;
use App\Http\Requests\Store\TwoFactorRequest;
use App\Http\Requests\Store\UpdatePasswordRequest;
use App\Http\Requests\Store\UpdateProfileRequest;
use App\Http\Resources\Store\AddressResource;
use App\Http\Resources\Store\UserResource;
use App\Services\TotpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => new UserResource($user),
            'addresses' => AddressResource::collection($user->addresses()->orderByDesc('is_default')->orderBy('id')->get()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->forceFill([
            'name' => $validated['name'] ?? $user->name,
            'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $user->phone,
            'company_type' => array_key_exists('companyType', $validated) ? $validated['companyType'] : $user->company_type,
            'company_name' => array_key_exists('companyName', $validated) ? $validated['companyName'] : $user->company_name,
            'department_name' => array_key_exists('departmentName', $validated) ? $validated['departmentName'] : $user->department_name,
            'npwp' => array_key_exists('npwp', $validated) ? $validated['npwp'] : $user->npwp,
            'transaction_code' => array_key_exists('transactionCode', $validated) ? $validated['transactionCode'] : $user->transaction_code,
            'tax_address' => array_key_exists('taxAddress', $validated) ? $validated['taxAddress'] : $user->tax_address,
            'attach_tax_invoice' => array_key_exists('attachTaxInvoice', $validated) ? $validated['attachTaxInvoice'] : $user->attach_tax_invoice,
        ])->save();

        return ApiResponse::success(new UserResource($user));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return ApiResponse::error('Password saat ini salah.', 422, [
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->forceFill(['password' => $request->string('password')->toString()])->save();

        $currentTokenId = $user->currentAccessToken()?->getKey();
        $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return ApiResponse::success(['updated' => true]);
    }

    public function enableTwoFactor(TwoFactorRequest $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return ApiResponse::error('Password salah.', 422, ['password' => ['Password salah.']]);
        }

        if ($user->hasEnabledTwoFactor()) {
            return ApiResponse::error('Two-factor authentication sudah aktif.', 422);
        }

        $secret = $totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();

        return ApiResponse::success([
            'secret' => $secret,
            'otpauthUrl' => $totp->otpauthUrl($user->email, $secret),
            'confirmed' => false,
        ]);
    }

    public function confirmTwoFactor(ConfirmTwoFactorRequest $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return ApiResponse::error('Tidak ada proses 2FA yang berjalan. Aktifkan terlebih dahulu.', 422);
        }

        if (! $totp->verify($user->two_factor_secret, $request->string('code')->toString())) {
            return ApiResponse::error('Kode OTP tidak valid.', 422, ['code' => ['Kode OTP tidak valid.']]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return ApiResponse::success(['enabled' => true]);
    }

    public function disableTwoFactor(TwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return ApiResponse::error('Password salah.', 422, ['password' => ['Password salah.']]);
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        return ApiResponse::success(['enabled' => false]);
    }
}
