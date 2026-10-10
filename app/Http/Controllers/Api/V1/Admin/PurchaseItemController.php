<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StorePurchaseItemRequest;
use App\Http\Resources\Admin\PurchaseItemResource;
use App\Models\PurchaseCategory;
use App\Models\PurchaseItem;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PurchaseItemController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = PurchaseItem::query()->with('category');

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('merk', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $items = $query->orderBy('name')->forPage($page, $limit)->get();

        return ApiResponse::success(PurchaseItemResource::collection($items), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StorePurchaseItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = PurchaseItem::query()->create([
            'purchase_category_id' => $this->categoryId($validated['category'] ?? null),
            'code' => DocumentNumber::next('purchase_item', PurchaseItem::query(), 'code', 4),
            'name' => $validated['product'],
            'description' => $validated['description'] ?? null,
            'merk' => $validated['merk'] ?? null,
            'price' => (int) ($validated['price'] ?? 0),
            'unit' => $validated['unit'] ?? null,
            'status' => 'Active',
        ]);

        return ApiResponse::success(new PurchaseItemResource($item->load('category')), [], 201);
    }

    public function update(StorePurchaseItemRequest $request, PurchaseItem $purchaseItem): JsonResponse
    {
        $validated = $request->validated();

        $purchaseItem->forceFill([
            'purchase_category_id' => $this->categoryId($validated['category'] ?? null) ?? $purchaseItem->purchase_category_id,
            'name' => $validated['product'],
            'description' => $validated['description'] ?? null,
            'merk' => $validated['merk'] ?? null,
            'price' => (int) ($validated['price'] ?? 0),
            'unit' => $validated['unit'] ?? null,
        ])->save();

        return ApiResponse::success(new PurchaseItemResource($purchaseItem->refresh()->load('category')));
    }

    public function destroy(PurchaseItem $purchaseItem): JsonResponse
    {
        $purchaseItem->delete();

        return ApiResponse::success(['id' => (string) $purchaseItem->id, 'removed' => true]);
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return PurchaseCategory::query()->firstOrCreate(['name' => trim($name)])->id;
    }
}
