<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\TaxRate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = TaxRate::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (TaxRate $rate) => [
            'id' => (string) $rate->id,
            'name' => $rate->name,
            'rate' => (float) $rate->rate,
            'status' => $rate->status,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $rate = TaxRate::query()->create([
            'name' => $validated['name'],
            'rate' => (float) $validated['rate'],
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $rate->id, 'name' => $rate->name, 'rate' => (float) $rate->rate], [], 201);
    }

    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $taxRate->forceFill([
            'name' => $validated['name'],
            'rate' => (float) $validated['rate'],
            'status' => $validated['status'] ?? $taxRate->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $taxRate->id, 'name' => $taxRate->name, 'rate' => (float) $taxRate->rate]);
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $taxRate->delete();

        return ApiResponse::success(['id' => (string) $taxRate->id, 'removed' => true]);
    }
}
