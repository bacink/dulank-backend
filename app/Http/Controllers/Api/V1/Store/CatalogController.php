<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\CatalogRegistry;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function show(string $kind): JsonResponse
    {
        $config = CatalogRegistry::resolve($kind);

        if ($config === null) {
            return ApiResponse::error('Unknown catalog', 404);
        }

        $rows = CatalogRegistry::rows($kind);
        $resource = $config['resource'];

        return ApiResponse::success($resource::collection($rows));
    }
}
