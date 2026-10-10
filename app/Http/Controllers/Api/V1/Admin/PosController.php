<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\HeldOrder;
use App\Models\Product;
use App\Services\TaxService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POS: katalog produk + held orders (parkir transaksi).
 */
class PosController extends Controller
{
    public function products(AdminIndexRequest $request): JsonResponse
    {
        $query = Product::query()->published()->with(['category', 'images']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term));
        }

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $products = $query->forPage($page, $limit)->get();

        return ApiResponse::success($products->map(fn (Product $product) => [
            'id' => (string) $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'category' => $product->category?->name,
            'price' => (int) ($product->price_from ?? $product->price),
            'quantity' => (float) $product->quantity,
            'image' => $product->image_path,
        ])->values(), ['page' => $page, 'limit' => $limit, 'total' => $total]);
    }

    public function heldOrders(AdminIndexRequest $request): JsonResponse
    {
        $query = HeldOrder::query()->with('cashier');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('customer', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (HeldOrder $held) => [
            'id' => (string) $held->id,
            'customer' => $held->customer,
            'cashier' => $held->cashier?->name,
            'itemCount' => count((array) $held->items),
            'total' => (int) $held->total,
            'method' => $held->method,
            'delivery' => $held->delivery,
            'note' => $held->note,
            'heldAt' => $held->held_at?->toIso8601String(),
            'items' => $held->items ?? [],
        ]));
    }

    public function storeHeldOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['sometimes', 'nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.spec' => ['sometimes', 'nullable', 'string', 'max:500'],
            'discount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'deliveryFee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'delivery' => ['sometimes', 'nullable', 'string', 'max:30'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $items = $validated['items'];
        $subTotal = (int) collect($items)->sum(fn (array $item) => (int) $item['price'] * (float) $item['quantity']);
        $discount = (int) ($validated['discount'] ?? 0);
        $deliveryFee = (int) ($validated['deliveryFee'] ?? 0);
        $tax = app(TaxService::class)->calculate(max($subTotal - $discount, 0));

        $held = HeldOrder::query()->create([
            'user_id' => $request->user()->id,
            'customer' => $validated['customer'] ?? null,
            'items' => $items,
            'sub_total' => $subTotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'total' => max($subTotal - $discount, 0) + $tax + $deliveryFee,
            'method' => $validated['method'] ?? null,
            'delivery' => $validated['delivery'] ?? null,
            'note' => $validated['note'] ?? null,
            'held_at' => now(),
        ]);

        return ApiResponse::success(['id' => (string) $held->id, 'total' => (int) $held->total], [], 201);
    }

    public function destroyHeldOrder(HeldOrder $heldOrder): JsonResponse
    {
        $heldOrder->delete();

        return ApiResponse::success(['id' => (string) $heldOrder->id, 'removed' => true]);
    }
}
