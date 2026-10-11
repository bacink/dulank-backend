<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Category;
use App\Models\FlowCategory;
use App\Models\FlowName;
use App\Models\FlowTemplate;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function categories(AdminIndexRequest $request): JsonResponse
    {
        $query = FlowCategory::query()->withCount('flowNames');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (FlowCategory $category) => [
            'id' => (string) $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'usedCount' => $category->flow_names_count,
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $category = FlowCategory::query()->create([
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name, 'code' => $category->code], [], 201);
    }

    public function updateCategory(Request $request, FlowCategory $flowCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $flowCategory->forceFill([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $flowCategory->code,
        ])->save();

        return ApiResponse::success(['id' => (string) $flowCategory->id, 'name' => $flowCategory->name, 'code' => $flowCategory->code]);
    }

    public function destroyCategory(FlowCategory $flowCategory): JsonResponse
    {
        $flowCategory->delete();

        return ApiResponse::success(['id' => (string) $flowCategory->id, 'removed' => true]);
    }

    public function names(AdminIndexRequest $request): JsonResponse
    {
        $query = FlowName::query()->with('category');

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (FlowName $flowName) => $this->flowNamePayload($flowName)));
    }

    public function storeName(Request $request): JsonResponse
    {
        $validated = $this->validateName($request);

        $category = FlowCategory::query()->firstOrCreate(['name' => $validated['category']]);

        $flowName = FlowName::query()->create([
            'code' => $validated['code'] ?? null,
            'flow_category_id' => $category->id,
            'name' => $validated['name'],
            'incentive' => (int) ($validated['incentive'] ?? 0),
            'unit' => $validated['unit'] ?? null,
            'flow_type' => $validated['flowType'] ?? 'Inhouse',
            'assignees' => $validated['assignees'] ?? null,
        ]);

        return ApiResponse::success($this->flowNamePayload($flowName->load('category')), [], 201);
    }

    public function updateName(Request $request, FlowName $flowName): JsonResponse
    {
        $validated = $this->validateName($request);

        $category = FlowCategory::query()->firstOrCreate(['name' => $validated['category']]);

        $flowName->forceFill([
            'code' => $validated['code'] ?? $flowName->code,
            'flow_category_id' => $category->id,
            'name' => $validated['name'],
            'incentive' => (int) ($validated['incentive'] ?? 0),
            'unit' => $validated['unit'] ?? null,
            'flow_type' => $validated['flowType'] ?? $flowName->flow_type,
            'assignees' => $validated['assignees'] ?? null,
        ])->save();

        return ApiResponse::success($this->flowNamePayload($flowName->refresh()->load('category')));
    }

    public function destroyName(FlowName $flowName): JsonResponse
    {
        $flowName->delete();

        return ApiResponse::success(['id' => (string) $flowName->id, 'removed' => true]);
    }

    public function templates(AdminIndexRequest $request): JsonResponse
    {
        $query = FlowTemplate::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (FlowTemplate $template) => [
            'id' => (string) $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'information' => $template->information,
        ]));
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'information' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $template = FlowTemplate::query()->create($validated);

        return ApiResponse::success(['id' => (string) $template->id, 'name' => $template->name, 'code' => $template->code], [], 201);
    }

    public function destroyTemplate(FlowTemplate $flowTemplate): JsonResponse
    {
        $flowTemplate->delete();

        return ApiResponse::success(['id' => (string) $flowTemplate->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateName(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'incentive' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'flowType' => ['sometimes', 'nullable', 'in:Inhouse,Outsource'],
            'assignees' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function flowNamePayload(FlowName $flowName): array
    {
        return [
            'id' => (string) $flowName->id,
            'code' => $flowName->code,
            'category' => $flowName->category?->name,
            'name' => $flowName->name,
            'incentive' => (int) $flowName->incentive,
            'unit' => $flowName->unit,
            'flowType' => $flowName->flow_type,
            'assignees' => $flowName->assignees ?? [],
        ];
    }

    /**
     * Konteks editor alur kerja: produk, kategori, kategori alur, nama alur & template.
     */
    public function context(): JsonResponse
    {
        return ApiResponse::success([
            'products' => Product::query()->where('status', 'Active')->limit(200)->get(['id', 'code', 'name', 'category_id'])
                ->map(fn (Product $product) => [
                    'id' => (string) $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                ])->values(),
            'productCategories' => Category::query()->orderBy('name')->pluck('name')->values(),
            'flowCategories' => FlowCategory::query()->orderBy('name')->pluck('name')->values(),
            'flows' => FlowName::query()->with('category')->orderBy('name')->limit(200)->get()
                ->map(fn (FlowName $flow) => [
                    'id' => (string) $flow->id,
                    'name' => $flow->name,
                    'category' => $flow->category?->name,
                    'templateNames' => [],
                ])->values(),
            'templates' => FlowTemplate::query()->orderBy('name')->pluck('name')->values(),
        ]);
    }
}
