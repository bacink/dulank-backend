<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StorePurchaseCategoryRequest;
use App\Http\Resources\Admin\PurchaseCategoryResource;
use App\Models\PurchaseCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PurchaseCategoryController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = PurchaseCategory::query()->withCount('items');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $categories = $query->orderBy('name')->forPage($page, $limit)->get();

        return ApiResponse::success(PurchaseCategoryResource::collection($categories), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StorePurchaseCategoryRequest $request): JsonResponse
    {
        $category = PurchaseCategory::query()->create([
            'name' => $request->string('name')->toString(),
            'status' => $request->input('status') ?? 'Active',
        ]);

        return ApiResponse::success(new PurchaseCategoryResource($category), [], 201);
    }

    public function update(StorePurchaseCategoryRequest $request, PurchaseCategory $purchaseCategory): JsonResponse
    {
        $purchaseCategory->forceFill([
            'name' => $request->string('name')->toString(),
            'status' => $request->input('status') ?? $purchaseCategory->status,
        ])->save();

        return ApiResponse::success(new PurchaseCategoryResource($purchaseCategory->refresh()));
    }

    public function destroy(PurchaseCategory $purchaseCategory): JsonResponse
    {
        $purchaseCategory->delete();

        return ApiResponse::success(['id' => (string) $purchaseCategory->id, 'removed' => true]);
    }
}
