<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\AddressResource;
use App\Http\Resources\Store\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find((int) $id);

        if ($user === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $this->authorize('view', $user);

        return ApiResponse::success(new UserResource($user));
    }

    public function addresses(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find((int) $id);

        if ($user === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $this->authorize('view', $user);

        return ApiResponse::success(AddressResource::collection($user->addresses()->orderBy('id')->get()));
    }
}
