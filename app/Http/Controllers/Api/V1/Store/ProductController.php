<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\ProductIndexRequest;
use App\Http\Resources\Store\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $query = Product::query()->published()->with(['specs', 'tags']);

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->where(function (Builder $query) use ($category): void {
                $query->where('category_id', (int) $category)
                    ->orWhereHas('category', fn (Builder $query) => $query->where('slug', $category));
            });
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('tags', fn (Builder $query) => $query->where('name', 'like', $term));
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $products = $query->forPage($page, $limit)->get();

        return ApiResponse::success(ProductResource::collection($products), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->published()
            ->with(['specs', 'tags'])
            ->where('slug', $slug)
            ->first();

        if ($product === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::success(new ProductResource($product));
    }
}
