<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\CartItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\AddCartItemRequest;
use App\Http\Requests\Store\UpdateCartItemRequest;
use App\Http\Resources\Store\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(CartItemResource::collection($this->items($request)));
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($request->integer('productId'));

        $spec = (string) ($request->input('spec') ?? '');
        $quantity = max($request->integer('quantity', 1), 1);

        $item = CartItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->where('spec', $spec)
            ->where('status', CartItemStatus::Active)
            ->first();

        if ($item !== null) {
            $item->forceFill(['quantity' => $item->quantity + $quantity])->save();
        } else {
            CartItem::query()->create([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->image_path,
                'quantity' => $quantity,
                'price' => $request->integer('price') ?: ($product->price_from ?? $product->price),
                'spec' => $spec,
                'status' => CartItemStatus::Active,
            ]);
        }

        return ApiResponse::success(CartItemResource::collection($this->items($request)));
    }

    public function update(UpdateCartItemRequest $request, string $itemId): JsonResponse
    {
        $item = $this->findItem($request, $itemId);

        $item->forceFill(['quantity' => max($request->integer('quantity'), 1)])->save();

        return ApiResponse::success(new CartItemResource($item));
    }

    public function destroy(Request $request, string $itemId): JsonResponse
    {
        $item = $this->findItem($request, $itemId);
        $item->delete();

        return ApiResponse::success(['id' => (string) $itemId, 'removed' => true]);
    }

    public function clear(Request $request): JsonResponse
    {
        CartItem::query()
            ->where('user_id', $request->user()->id)
            ->where('status', CartItemStatus::Active)
            ->delete();

        return ApiResponse::success(['cleared' => true]);
    }

    /**
     * @return Collection<int, CartItem>
     */
    private function items(Request $request): Collection
    {
        return CartItem::query()
            ->where('user_id', $request->user()->id)
            ->where('status', CartItemStatus::Active)
            ->orderBy('id')
            ->get();
    }

    private function findItem(Request $request, string $itemId): CartItem
    {
        $item = CartItem::query()
            ->where('user_id', $request->user()->id)
            ->where('status', CartItemStatus::Active)
            ->whereKey($itemId)
            ->first();

        if ($item === null) {
            abort(404, 'Cart item not found.');
        }

        return $item;
    }
}
