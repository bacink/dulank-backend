<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\WishlistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreWishlistRequest;
use App\Http\Resources\Store\WishlistResource;
use App\Models\Product;
use App\Models\Wishlist;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('id')
            ->get();

        return ApiResponse::success(WishlistResource::collection($items));
    }

    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $user = $request->user();
        $product = Product::query()->find($request->integer('productId'));

        if ($product === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        $spec = (string) ($request->input('spec') ?? '');

        $wishlist = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('spec', $spec)
            ->first();

        $created = false;

        if ($wishlist === null) {
            $wishlist = Wishlist::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->image_path,
                'price' => (int) ($product->price_from ?? $product->price),
                'spec' => $spec,
                'status' => WishlistStatus::Active,
                'added_at' => now()->toDateString(),
                'expire_date' => $request->date('expireDate'),
            ]);
            $created = true;
        } elseif ($request->filled('expireDate')) {
            $wishlist->forceFill(['expire_date' => $request->date('expireDate')])->save();
        }

        return ApiResponse::success(new WishlistResource($wishlist), [], $created ? 201 : 200);
    }

    public function destroy(Request $request, string $itemId): JsonResponse
    {
        $item = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($itemId)
            ->first();

        if ($item === null) {
            return ApiResponse::error('Wishlist item not found.', 404);
        }

        $this->authorize('delete', $item);
        $item->delete();

        return ApiResponse::success(['id' => (string) $itemId, 'removed' => true]);
    }
}
