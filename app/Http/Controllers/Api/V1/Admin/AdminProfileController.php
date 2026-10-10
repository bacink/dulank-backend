<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\UserResource;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Profil & upload admin (banner/produk/avatar).
 */
class AdminProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => new UserResource($user),
            'roles' => $user?->getRoleNames() ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'name' => $validated['name'] ?? $user->name,
            'phone' => $validated['phone'] ?? $user->phone,
        ])->save();

        return ApiResponse::success(new UserResource($user));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error('Password saat ini salah.', 422, [
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        return ApiResponse::success(['updated' => true]);
    }

    public function uploadAvatar(Request $request, MediaService $media): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $path = $media->store($request->file('avatar'), 'avatars/'.$user->id);
        $user->forceFill(['avatar_path' => $path])->save();

        return ApiResponse::success(['path' => $path, 'url' => $media->url($path), 'avatar' => $media->url($path)], [], 201);
    }

    public function uploadImage(Request $request, MediaService $media): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.(int) config('dulank.uploads.max_kb', 4096)],
            'folder' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        $file = $request->file('file');
        $folder = trim((string) $request->input('folder', 'uploads'), '/');
        $path = $media->store($file, $folder === '' ? 'uploads' : $folder);

        return ApiResponse::success([
            'path' => $path,
            'url' => $media->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ], [], 201);
    }
}
