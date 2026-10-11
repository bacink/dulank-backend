<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PaperGroupResource;
use App\Models\PaperGroup;
use App\Models\Partner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD kalkulator sisi store: gugus kertas milik merchant.
 * Terminologi API publik = `merchant_id`; internal model/tabel = partners.
 */
class CalculatorPaperGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PaperGroup::query()->orderBy('id');

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('partner_id', $merchantId);
        }

        return ApiResponse::success(PaperGroupResource::collection($query->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'integer', 'exists:partners,id'],
            'name' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'price_type' => ['nullable', 'in:fixed,range,calculator'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ]);

        $partner = Partner::findOrFail($data['merchant_id']);

        $group = PaperGroup::create([
            'owner_type' => 'vendor',
            'partner_id' => $data['merchant_id'],
            'source' => $partner->name,
            'name' => $data['name'],
            'merk' => $data['merk'] ?? null,
            'price_type' => $data['price_type'] ?? null,
            'status' => $data['status'] ?? 'Active',
        ]);

        return ApiResponse::success(new PaperGroupResource($group), [], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $group = PaperGroup::query()->find($id);

        if ($group === null) {
            return ApiResponse::error('Paper group not found.', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'price_type' => ['nullable', 'in:fixed,range,calculator'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ]);

        $group->update($data);

        return ApiResponse::success(new PaperGroupResource($group->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        $group = PaperGroup::query()->find($id);

        if ($group === null) {
            return ApiResponse::error('Paper group not found.', 404);
        }

        $group->delete();

        return ApiResponse::success(['id' => (string) $id, 'removed' => true]);
    }
}