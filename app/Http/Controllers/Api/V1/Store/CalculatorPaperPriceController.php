<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PaperPriceResource;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\Partner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD kalkulator sisi store: harga kertas milik partner.
 * Kepemilikan diturunkan dari paper_group.partner_id.
 */
class CalculatorPaperPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PaperPrice::query()->with('group')->orderBy('id');

        if ($merchantId = $request->integer('merchant_id')) {
            $query->whereHas('group', fn ($q) => $q->where('partner_id', $merchantId));
        }

        return ApiResponse::success(PaperPriceResource::collection($query->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'paper_group_id' => ['required', 'integer', 'exists:paper_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'min_order' => ['nullable', 'integer', 'min:0'],
            'multiple' => ['nullable', 'integer', 'min:0'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $group = PaperGroup::findOrFail($data['paper_group_id']);
        $partner = Partner::find($group->partner_id);

        $price = PaperPrice::create([
            'paper_group_id' => $data['paper_group_id'],
            'source' => $partner?->name ?? $group->source,
            'name' => $data['name'],
            'min_order' => $data['min_order'] ?? null,
            'multiple' => $data['multiple'] ?? null,
            'price' => $data['price'],
            'published' => $data['published'] ?? true,
            'active' => $data['active'] ?? true,
            'status' => 'Active',
        ]);

        return ApiResponse::success(new PaperPriceResource($price->load('group')), [], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $price = PaperPrice::query()->with('group')->find($id);

        if ($price === null) {
            return ApiResponse::error('Paper price not found.', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'min_order' => ['nullable', 'integer', 'min:0'],
            'multiple' => ['nullable', 'integer', 'min:0'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $price->update($data);

        return ApiResponse::success(new PaperPriceResource($price->refresh()->load('group')));
    }

    public function destroy(int $id): JsonResponse
    {
        $price = PaperPrice::query()->find($id);

        if ($price === null) {
            return ApiResponse::error('Paper price not found.', 404);
        }

        $price->delete();

        return ApiResponse::success(['id' => (string) $id, 'removed' => true]);
    }
}