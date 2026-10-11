<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\MachineResource;
use App\Models\Machine;
use App\Models\Partner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD kalkulator sisi store: mesin milik merchant (printing shop / paper store).
 * Terminologi API publik = `merchant_id`; internal model/tabel = partners.
 */
class CalculatorMachineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Machine::query()->orderBy('id');

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('partner_id', $merchantId);
        }

        $rows = $query->get();

        return ApiResponse::success(MachineResource::collection($rows));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'integer', 'exists:partners,id'],
            'kind' => ['required', 'in:printing,lamination,die-cutting,foil'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'integer', 'min:0'],
            'rate_cm' => ['nullable', 'numeric', 'min:0'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $partner = Partner::findOrFail($data['merchant_id']);

        $machine = Machine::create([
            'owner_type' => 'vendor',
            'partner_id' => $data['merchant_id'],
            'kind' => $data['kind'],
            'source' => $partner->name,
            'name' => $data['name'],
            'size' => $data['size'] ?? null,
            'price' => $data['price'] ?? null,
            'unit_price' => $data['unit_price'] ?? null,
            'rate_cm' => $data['rate_cm'] ?? null,
            'published' => $data['published'] ?? true,
            'active' => $data['active'] ?? true,
            'status' => 'Active',
        ]);

        return ApiResponse::success(new MachineResource($machine), [], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $machine = Machine::query()->find($id);

        if ($machine === null) {
            return ApiResponse::error('Machine not found.', 404);
        }

        $data = $request->validate([
            'kind' => ['sometimes', 'in:printing,lamination,die-cutting,foil'],
            'name' => ['sometimes', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'integer', 'min:0'],
            'rate_cm' => ['nullable', 'numeric', 'min:0'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $machine->update($data);

        return ApiResponse::success(new MachineResource($machine->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        $machine = Machine::query()->find($id);

        if ($machine === null) {
            return ApiResponse::error('Machine not found.', 404);
        }

        $machine->delete();

        return ApiResponse::success(['id' => (string) $id, 'removed' => true]);
    }
}