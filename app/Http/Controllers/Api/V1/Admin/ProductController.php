<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Product::query()->with(['category', 'subCategory', 'unit', 'store', 'images', 'variants']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('code', 'like', $term)
                ->orWhere('slug', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $products = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($products->map(fn (Product $product) => $this->payload($product))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $product = DB::transaction(function () use ($request, $validated): Product {
            $product = Product::query()->create($this->attributes($request, $validated, null));

            $this->syncImages($product, $validated['images'] ?? []);
            $this->syncVariants($product, $validated['variants'] ?? []);
            $this->syncPriceRange($product);

            return $product->load(['category', 'subCategory', 'unit', 'store', 'images', 'variants']);
        });

        return ApiResponse::success($this->payload($product), [], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success($this->payload($product->load(['category', 'subCategory', 'unit', 'store', 'images', 'variants', 'processes'])));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($request, $product, $validated): void {
            $product->forceFill($this->attributes($request, $validated, $product))->save();

            if (array_key_exists('images', $validated)) {
                $this->syncImages($product, $validated['images'] ?? []);
            }

            if (array_key_exists('variants', $validated)) {
                $this->syncVariants($product, $validated['variants'] ?? []);
            }

            $this->syncPriceRange($product);
        });

        return ApiResponse::success($this->payload($product->refresh()->load(['category', 'subCategory', 'unit', 'store', 'images', 'variants'])));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return ApiResponse::success(['id' => (string) $product->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'categoryId' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'subCategory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subCategoryId' => ['sometimes', 'nullable', 'integer', 'exists:sub_categories,id'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unitId' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'store' => ['sometimes', 'nullable', 'string', 'max:255'],
            'storeId' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'priceType' => ['sometimes', 'nullable', 'in:Fixed,Range,Calculator'],
            'sellingType' => ['sometimes', 'nullable', 'string', 'max:40'],
            'quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minOrderQty' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantityAlert' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'druckPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minLength' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minWidth' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discountType' => ['sometimes', 'nullable', 'string', 'max:20'],
            'discountValue' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'taxType' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'archivedAt' => ['sometimes', 'nullable', 'date'],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*' => ['string', 'max:255'],
            'variants' => ['sometimes', 'nullable', 'array'],
            'variants.*.variation' => ['required', 'string', 'max:120'],
            'variants.*.value' => ['required', 'string', 'max:255'],
            'variants.*.quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.price' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(Request $request, array $validated, ?Product $product): array
    {
        return [
            'code' => $validated['code'] ?? $product?->code ?? DocumentNumber::next('product', Product::query(), 'code', 5),
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['name'], $product?->id),
            'name' => $validated['name'],
            'category_id' => $validated['categoryId'] ?? $this->categoryId($validated['category'] ?? null) ?? $product?->category_id,
            'sub_category_id' => $validated['subCategoryId'] ?? $this->subCategoryId($validated['subCategory'] ?? null, $validated['categoryId'] ?? null) ?? $product?->sub_category_id,
            'unit_id' => $validated['unitId'] ?? $this->unitId($validated['unit'] ?? null) ?? $product?->unit_id,
            'store_id' => $validated['storeId'] ?? $this->storeId($validated['store'] ?? null) ?? $product?->store_id,
            'description' => $validated['description'] ?? null,
            'price' => (int) ($validated['price'] ?? $product?->price ?? 0),
            'price_type' => $validated['priceType'] ?? $product?->price_type?->value ?? 'Fixed',
            'selling_type' => $validated['sellingType'] ?? null,
            'quantity' => (float) ($validated['quantity'] ?? $product?->quantity ?? 0),
            'min_order_qty' => isset($validated['minOrderQty']) ? (float) $validated['minOrderQty'] : $product?->min_order_qty,
            'quantity_alert' => isset($validated['quantityAlert']) ? (float) $validated['quantityAlert'] : $product?->quantity_alert,
            'min_price' => $validated['minPrice'] ?? $product?->min_price,
            'druck_price' => $validated['druckPrice'] ?? $product?->druck_price,
            'min_length' => isset($validated['minLength']) ? (float) $validated['minLength'] : $product?->min_length,
            'min_width' => isset($validated['minWidth']) ? (float) $validated['minWidth'] : $product?->min_width,
            'discount_type' => $validated['discountType'] ?? null,
            'discount_value' => (int) ($validated['discountValue'] ?? $product?->discount_value ?? 0),
            'tax_type' => $validated['taxType'] ?? null,
            'status' => $validated['status'] ?? $product?->status?->value ?? 'Active',
            'archived_at' => $request->date('archivedAt')?->toDateTimeString() ?? $product?->archived_at,
        ];
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'produk';
        $slug = $base;
        $suffix = 1;

        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $category = Category::query()->firstOrCreate(
            ['name' => trim($name)],
            ['code' => 'CAT-'.strtoupper(Str::random(6)), 'slug' => Str::slug($name).'-'.strtolower(Str::random(4))],
        );

        return $category->id;
    }

    private function subCategoryId(?string $name, ?int $categoryId): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return SubCategory::query()->firstOrCreate(
            ['name' => trim($name)],
            ['category_id' => $categoryId ?? $this->categoryId('Umum')],
        )->id;
    }

    private function unitId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return Unit::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    private function storeId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return Store::query()->firstOrCreate(['store_name' => trim($name)])->id;
    }

    /**
     * @param  array<int, string>  $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach (array_values($images) as $index => $path) {
            $product->images()->create(['path' => $path, 'sort_order' => $index]);
        }

        if ($images !== [] && $product->image_path === null) {
            $product->forceFill(['image_path' => $images[0]])->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $product->variants()->delete();

        foreach ($variants as $index => $variant) {
            $product->variants()->create([
                'variation' => $variant['variation'],
                'value' => $variant['value'],
                'quantity' => (float) ($variant['quantity'] ?? 0),
                'price' => (int) ($variant['price'] ?? 0),
                'sort_order' => $index,
            ]);
        }
    }

    private function syncPriceRange(Product $product): void
    {
        $product->loadMissing('variants');

        if ($product->variants->isEmpty()) {
            $product->forceFill([
                'price_from' => (int) $product->price,
                'price_to' => (int) $product->price,
            ])->save();

            return;
        }

        $product->forceFill([
            'price_from' => (int) $product->variants->min('price'),
            'price_to' => (int) $product->variants->max('price'),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'slug' => $product->slug,
            'categoryId' => $product->category_id !== null ? (string) $product->category_id : null,
            'category' => $product->category?->name,
            'subCategoryId' => $product->sub_category_id !== null ? (string) $product->sub_category_id : null,
            'subCategory' => $product->subCategory?->name,
            'unitId' => $product->unit_id !== null ? (string) $product->unit_id : null,
            'unit' => $product->unit?->name,
            'storeId' => $product->store_id !== null ? (string) $product->store_id : null,
            'store' => $product->store?->store_name,
            'price' => (int) $product->price,
            'priceType' => $product->price_type?->value,
            'sellingType' => $product->selling_type,
            'description' => $product->description,
            'quantity' => (float) $product->quantity,
            'minOrderQty' => $product->min_order_qty !== null ? (float) $product->min_order_qty : null,
            'quantityAlert' => $product->quantity_alert !== null ? (float) $product->quantity_alert : null,
            'minPrice' => $product->min_price,
            'druckPrice' => $product->druck_price,
            'minLength' => $product->min_length !== null ? (float) $product->min_length : null,
            'minWidth' => $product->min_width !== null ? (float) $product->min_width : null,
            'discountType' => $product->discount_type,
            'discountValue' => (int) $product->discount_value,
            'taxType' => $product->tax_type,
            'status' => $product->status?->value,
            'archivedAt' => $product->archived_at?->toIso8601String(),
            'created' => $product->created_at?->toDateString(),
            'createdAt' => $product->created_at?->toIso8601String(),
            'updatedAt' => $product->updated_at?->toIso8601String(),
            'images' => $product->relationLoaded('images') ? $product->images->pluck('path')->values() : [],
            'variants' => $product->relationLoaded('variants') ? $product->variants->map(fn ($variant) => [
                'id' => (string) $variant->id,
                'variation' => $variant->variation,
                'value' => $variant->value,
                'quantity' => (float) $variant->quantity,
                'price' => (int) $variant->price,
            ])->values() : [],
            'processes' => $product->relationLoaded('processes') ? $product->processes->map(fn ($process) => [
                'id' => (string) $process->id,
                'code' => $process->code,
                'processName' => $process->process_name,
                'image' => $process->image_path,
                'status' => $process->status,
            ])->values() : [],
        ];
    }
}
