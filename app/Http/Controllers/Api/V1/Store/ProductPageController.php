<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = Setting::query()
            ->where('group', 'product-pages')
            ->where('key', $slug)
            ->first();

        if ($page === null) {
            return ApiResponse::error('Product page not found.', 404);
        }

        return ApiResponse::success($page->value);
    }
}