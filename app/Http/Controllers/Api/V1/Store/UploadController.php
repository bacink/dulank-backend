<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UploadArtworkRequest;
use App\Http\Requests\Store\UploadAvatarRequest;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function avatar(UploadAvatarRequest $request, MediaService $media): JsonResponse
    {
        $user = $request->user();
        $path = $media->store($request->file('avatar'), 'avatars/'.$user->id);

        $user->forceFill(['avatar_path' => $path])->save();

        return ApiResponse::success([
            'path' => $path,
            'url' => $media->url($path),
            'avatar' => $media->url($path),
        ], [], 201);
    }

    public function artwork(UploadArtworkRequest $request, MediaService $media): JsonResponse
    {
        $file = $request->file('file');
        $path = $media->store($file, 'artworks/'.$request->user()->id);

        return ApiResponse::success([
            'path' => $path,
            'url' => $media->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ], [], 201);
    }
}
