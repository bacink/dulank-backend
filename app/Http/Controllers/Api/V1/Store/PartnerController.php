<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PaperStoreResource;
use App\Http\Resources\Store\PrintingShopResource;
use App\Models\Partner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PartnerController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $partner = Partner::query()->with('metrics')->find($id);

        if ($partner === null) {
            return ApiResponse::error('Partner not found.', 404);
        }

        $resource = $partner->type === 'paper_store'
            ? PaperStoreResource::class
            : PrintingShopResource::class;

        return ApiResponse::success((new $resource($partner))->resolve());
    }
}
