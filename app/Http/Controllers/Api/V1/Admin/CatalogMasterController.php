<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Category;
use App\Models\ProductProcess;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\WorkshopService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Master katalog admin: categories, sub-categories, units, variants,
 * product-processes, workshop-services.
 */
class CatalogMasterController extends Controller
{
    public function categories(AdminIndexRequest $request): JsonResponse
    {
        $query = Category::query()->withCount('products');

        $this->applySearch($query, $request, ['name', 'code']);
        $this->applyStatus($query, $request);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Category $category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'code' => $category->code,
            'createdDate' => $category->created_at?->toDateString(),
            'createdBy' => $category->created_at !== null ? 'Admin' : null,
            'productCount' => $category->products_count,
            'status' => $category->status,
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $category = Category::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? 'CAT-'.strtoupper(Str::random(6)),
            'slug' => $this->uniqueSlug(Category::class, $validated['name'], 'slug'),
            'created_by' => $request->user()->id,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name, 'code' => $category->code], [], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $category->forceFill([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $category->code,
            'status' => $validated['status'] ?? $category->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name, 'code' => $category->code]);
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        $category->delete();

        return ApiResponse::success(['id' => (string) $category->id, 'removed' => true]);
    }

    public function subCategories(AdminIndexRequest $request): JsonResponse
    {
        $query = SubCategory::query()->with('category')->withCount('products');

        $this->applySearch($query, $request, ['name', 'code']);
        $this->applyStatus($query, $request);

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (SubCategory $subCategory) => [
            'id' => (string) $subCategory->id,
            'name' => $subCategory->name,
            'categoryId' => (string) $subCategory->category_id,
            'category' => $subCategory->category?->name,
            'categoryCode' => $subCategory->category?->code,
            'description' => $subCategory->description,
            'itemUsed' => $subCategory->item_used,
            'createdBy' => 'Admin',
            'status' => $subCategory->status,
        ]));
    }

    public function storeSubCategory(Request $request): JsonResponse
    {
        $validated = $this->validateSubCategory($request);

        $subCategory = SubCategory::query()->create([
            'category_id' => $this->categoryId($validated['category']),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $subCategory->id, 'name' => $subCategory->name], [], 201);
    }

    public function updateSubCategory(Request $request, SubCategory $subCategory): JsonResponse
    {
        $validated = $this->validateSubCategory($request);

        $subCategory->forceFill([
            'category_id' => $this->categoryId($validated['category']),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $subCategory->code,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $subCategory->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $subCategory->id, 'name' => $subCategory->name]);
    }

    public function destroySubCategory(SubCategory $subCategory): JsonResponse
    {
        $subCategory->delete();

        return ApiResponse::success(['id' => (string) $subCategory->id, 'removed' => true]);
    }

    public function units(AdminIndexRequest $request): JsonResponse
    {
        $query = Unit::query()->withCount('products');

        $this->applySearch($query, $request, ['name', 'short_name']);
        $this->applyStatus($query, $request);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Unit $unit) => [
            'id' => (string) $unit->id,
            'name' => $unit->name,
            'shortName' => $unit->short_name,
            'itemUsed' => $unit->item_used ?: $unit->products_count,
            'createdOn' => $unit->created_at?->toDateString(),
            'status' => $unit->status,
        ]));
    }

    public function storeUnit(Request $request): JsonResponse
    {
        if ($id = $request->input('id')) {
            $unit = Unit::query()->findOrFail($id);
            return $this->updateUnit($request, $unit);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'shortName' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $unit = Unit::query()->create([
            'name' => $validated['name'],
            'short_name' => $validated['shortName'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $unit->id, 'name' => $unit->name], [], 201);
    }

    public function updateUnit(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'shortName' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $unit->forceFill([
            'name' => $validated['name'],
            'short_name' => $validated['shortName'] ?? null,
            'status' => $validated['status'] ?? $unit->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $unit->id, 'name' => $unit->name]);
    }

    public function destroyUnit(Unit $unit): JsonResponse
    {
        $unit->delete();

        return ApiResponse::success(['id' => (string) $unit->id, 'removed' => true]);
    }

    public function variants(AdminIndexRequest $request): JsonResponse
    {
        $query = Variant::query();

        $this->applySearch($query, $request, ['name']);
        $this->applyStatus($query, $request);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Variant $variant) => [
            'id' => (string) $variant->id,
            'name' => $variant->name,
            'values' => implode(', ', (array) ($variant->values ?? [])),
            'itemUsed' => $variant->item_used,
            'createdOn' => $variant->created_at?->toDateString(),
            'status' => $variant->status,
        ]));
    }

    public function storeVariant(Request $request): JsonResponse
    {
        $validated = $this->validateVariant($request);

        $variant = Variant::query()->create([
            'name' => $validated['name'],
            'values' => $this->splitValues($validated['values']),
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $variant->id, 'name' => $variant->name], [], 201);
    }

    public function updateVariant(Request $request, Variant $variant): JsonResponse
    {
        $validated = $this->validateVariant($request);

        $variant->forceFill([
            'name' => $validated['name'],
            'values' => $this->splitValues($validated['values']),
            'status' => $validated['status'] ?? $variant->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $variant->id, 'name' => $variant->name]);
    }

    public function destroyVariant(Variant $variant): JsonResponse
    {
        $variant->delete();

        return ApiResponse::success(['id' => (string) $variant->id, 'removed' => true]);
    }

    public function productProcesses(AdminIndexRequest $request): JsonResponse
    {
        $query = ProductProcess::query()->with('product');

        $this->applySearch($query, $request, ['code', 'process_name']);
        $this->applyStatus($query, $request);

        $product = trim((string) $request->input('product', ''));
        if ($product !== '') {
            $query->whereHas('product', fn (Builder $query) => $query->where('name', $product));
        }

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (ProductProcess $process) => [
            'id' => (string) $process->id,
            'productId' => (string) $process->product_id,
            'product' => $process->product?->name,
            'code' => $process->code,
            'image' => $process->image_path,
            'processName' => $process->process_name,
            'status' => $process->status ?? 'Active',
            'createDate' => $process->created_at?->toDateString(),
        ]));
    }

    public function storeProductProcess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'processName' => ['required', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive'],
        ]);

        $process = ProductProcess::query()->create([
            'product_id' => (int) $validated['productId'],
            'code' => $validated['code'] ?? null,
            'process_name' => $validated['processName'],
            'image_path' => $validated['image'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $process->id, 'processName' => $process->process_name], [], 201);
    }

    public function updateProductProcess(Request $request, ProductProcess $productProcess): JsonResponse
    {
        $validated = $request->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'processName' => ['required', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive'],
        ]);

        $productProcess->forceFill([
            'product_id' => (int) $validated['productId'],
            'code' => $validated['code'] ?? null,
            'process_name' => $validated['processName'],
            'image_path' => $validated['image'] ?? null,
            'status' => $validated['status'] ?? $productProcess->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $productProcess->id, 'processName' => $productProcess->process_name]);
    }

    public function destroyProductProcess(ProductProcess $productProcess): JsonResponse
    {
        $productProcess->delete();

        return ApiResponse::success(['id' => (string) $productProcess->id, 'removed' => true]);
    }

    public function workshopServices(AdminIndexRequest $request): JsonResponse
    {
        $query = WorkshopService::query()->with('store');

        $this->applySearch($query, $request, ['name']);
        $this->applyStatus($query, $request);

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (WorkshopService $service) => $this->workshopPayload($service)));
    }

    public function storeWorkshopService(Request $request): JsonResponse
    {
        $validated = $this->validateWorkshop($request);

        $service = WorkshopService::query()->create($this->workshopAttributes($validated));

        return ApiResponse::success($this->workshopPayload($service->load('store')), [], 201);
    }

    public function updateWorkshopService(Request $request, WorkshopService $workshopService): JsonResponse
    {
        $validated = $this->validateWorkshop($request);

        $workshopService->forceFill($this->workshopAttributes($validated) + [
            'archived_at' => $request->date('archivedAt')?->toDateTimeString() ?? $workshopService->archived_at,
        ])->save();

        return ApiResponse::success($this->workshopPayload($workshopService->refresh()->load('store')));
    }

    public function destroyWorkshopService(WorkshopService $workshopService): JsonResponse
    {
        $workshopService->delete();

        return ApiResponse::success(['id' => (string) $workshopService->id, 'removed' => true]);
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, AdminIndexRequest $request, array $columns): void
    {
        $search = trim((string) $request->input('search', ''));
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $term);
            }
        });
    }

    /**
     * @param  Builder<*>  $query
     */
    private function applyStatus(Builder $query, AdminIndexRequest $request): void
    {
        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubCategory(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVariant(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'values' => ['required', 'string', 'max:2000'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function splitValues(string $values): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $values))));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWorkshop(Request $request): array
    {
        return $request->validate([
            'storeId' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'category' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'printingType' => ['sometimes', 'nullable', 'string', 'max:40'],
            'maxHeight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'maxWidth' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sizeUnit' => ['sometimes', 'nullable', 'in:cm,mm'],
            'quantityMinimum' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'priceUnit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'druckPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minSize' => ['sometimes', 'nullable', 'string', 'max:60'],
            'maxSize' => ['sometimes', 'nullable', 'string', 'max:60'],
            'pricePerCm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimumPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'standardPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'standardMinimum' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'halfCutPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'halfCutMinimum' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimumCalculation' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function workshopAttributes(array $validated): array
    {
        return [
            'store_id' => $validated['storeId'] ?? null,
            'category' => $validated['category'],
            'name' => $validated['name'],
            'printing_type' => $validated['printingType'] ?? null,
            'max_height' => isset($validated['maxHeight']) ? (float) $validated['maxHeight'] : null,
            'max_width' => isset($validated['maxWidth']) ? (float) $validated['maxWidth'] : null,
            'size_unit' => $validated['sizeUnit'] ?? null,
            'quantity_minimum' => isset($validated['quantityMinimum']) ? (float) $validated['quantityMinimum'] : null,
            'price' => (int) ($validated['price'] ?? 0),
            'price_unit' => $validated['priceUnit'] ?? null,
            'druck_price' => isset($validated['druckPrice']) ? (int) $validated['druckPrice'] : null,
            'min_size' => $validated['minSize'] ?? null,
            'max_size' => $validated['maxSize'] ?? null,
            'price_per_cm' => isset($validated['pricePerCm']) ? (int) $validated['pricePerCm'] : null,
            'minimum_price' => isset($validated['minimumPrice']) ? (int) $validated['minimumPrice'] : null,
            'standard_price' => isset($validated['standardPrice']) ? (int) $validated['standardPrice'] : null,
            'standard_minimum' => isset($validated['standardMinimum']) ? (int) $validated['standardMinimum'] : null,
            'half_cut_price' => isset($validated['halfCutPrice']) ? (int) $validated['halfCutPrice'] : null,
            'half_cut_minimum' => isset($validated['halfCutMinimum']) ? (int) $validated['halfCutMinimum'] : null,
            'minimum_calculation' => isset($validated['minimumCalculation']) ? (int) $validated['minimumCalculation'] : null,
            'status' => $validated['status'] ?? 'Active',
        ];
    }

    private function categoryId(string $name): int
    {
        return Category::query()->firstOrCreate(
            ['name' => trim($name)],
            ['code' => 'CAT-'.strtoupper(Str::random(6)), 'slug' => $this->uniqueSlug(Category::class, $name, 'slug')],
        )->id;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function uniqueSlug(string $model, string $value, string $column): string
    {
        $base = Str::slug($value) ?: 'item';
        $slug = $base;
        $suffix = 1;

        while ($model::query()->where($column, $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function workshopPayload(WorkshopService $service): array
    {
        return [
            'id' => (string) $service->id,
            'storeId' => $service->store_id !== null ? (string) $service->store_id : null,
            'store' => $service->store?->store_name,
            'category' => $service->category,
            'name' => $service->name,
            'printingType' => $service->printing_type,
            'maxHeight' => $service->max_height !== null ? (float) $service->max_height : null,
            'maxWidth' => $service->max_width !== null ? (float) $service->max_width : null,
            'sizeUnit' => $service->size_unit,
            'quantityMinimum' => $service->quantity_minimum !== null ? (float) $service->quantity_minimum : null,
            'price' => (int) $service->price,
            'priceUnit' => $service->price_unit,
            'druckPrice' => $service->druck_price,
            'minSize' => $service->min_size,
            'maxSize' => $service->max_size,
            'pricePerCm' => $service->price_per_cm,
            'minimumPrice' => $service->minimum_price,
            'standardPrice' => $service->standard_price,
            'standardMinimum' => $service->standard_minimum,
            'halfCutPrice' => $service->half_cut_price,
            'halfCutMinimum' => $service->half_cut_minimum,
            'minimumCalculation' => $service->minimum_calculation,
            'status' => $service->status,
            'updatedAt' => $service->updated_at?->toIso8601String(),
            'archivedAt' => $service->archived_at?->toIso8601String(),
        ];
    }
}
