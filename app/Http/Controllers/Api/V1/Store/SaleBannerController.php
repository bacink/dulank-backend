<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\BannerType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Store\SaleBannerResource;
use App\Models\Banner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SaleBannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::query()
            ->where('type', BannerType::Product)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(SaleBannerResource::collection($banners));
    }
}
