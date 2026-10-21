<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Resources\Admin\SupplierResource;
use App\Models\Supplier;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Supplier::query();

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $suppliers = $query->orderBy('name')->forPage($page, $limit)->get();

        return ApiResponse::success(SupplierResource::collection($suppliers), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $supplier = Supplier::query()->create([
            'code' => $validated['supplierId'] ?? DocumentNumber::next('supplier', Supplier::query(), 'code', 3),
            'name' => $validated['name'],
            'photo_path' => $validated['photo'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'pic_name' => $validated['picName'] ?? null,
            'status' => $validated['status'] ?? 'Active',
            'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
        ]);

        return ApiResponse::success(new SupplierResource($supplier), [], 201);
    }

    public function show(string $supplierId): JsonResponse
    {
        $supplier = Supplier::query()->where('code', $supplierId)->firstOrFail();

        return ApiResponse::success(new SupplierResource($supplier));
    }

    public function update(StoreSupplierRequest $request, string $supplierId): JsonResponse
    {
        $supplier = Supplier::query()->where('code', $supplierId)->firstOrFail();
        $validated = $request->validated();

        $supplier->forceFill([
            'code' => $validated['supplierId'] ?? $supplier->code,
            'name' => $validated['name'],
            'photo_path' => array_key_exists('photo', $validated) ? $validated['photo'] : $supplier->photo_path,
            'email' => $validated['email'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'pic_name' => $validated['picName'] ?? null,
            'status' => $validated['status'] ?? $supplier->status,
            'date' => $request->date('date')?->toDateString() ?? $supplier->date?->toDateString(),
        ])->save();

        return ApiResponse::success(new SupplierResource($supplier->refresh()));
    }

    public function destroy(string $supplierId): JsonResponse
    {
        $supplier = Supplier::query()->where('code', $supplierId)->firstOrFail();
        MasterDataService::assertDeletable($supplier);
        $supplier->delete();

        return ApiResponse::success(['id' => $supplierId, 'removed' => true]);
    }
}
