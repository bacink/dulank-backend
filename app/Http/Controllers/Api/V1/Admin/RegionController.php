<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wilayah (provinsi/kabupaten/kecamatan) untuk dropdown alamat.
 */
class RegionController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $query = Province::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Province $province) => $this->provincePayload($province))->values());
    }

    public function storeProvince(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $province = Province::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
        ]);

        return ApiResponse::success($this->provincePayload($province), [], 201);
    }

    public function destroyProvince(Province $province): JsonResponse
    {
        $province->delete();

        return ApiResponse::success(['id' => (string) $province->id, 'removed' => true]);
    }

    public function regencies(Request $request): JsonResponse
    {
        $query = Regency::query()->with('province');

        $province = trim((string) $request->input('province', ''));
        if ($province !== '') {
            $query->whereHas('province', fn (Builder $query) => $query->where('name', $province)->orWhere('code', $province));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Regency $regency) => $this->regencyPayload($regency))->values());
    }

    public function storeRegency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'province' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $regency = Regency::query()->create([
            'province_id' => Province::query()->where('name', $validated['province'])->orWhere('code', $validated['province'])->value('id'),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'type' => $validated['type'] ?? null,
        ]);

        return ApiResponse::success($this->regencyPayload($regency->load('province')), [], 201);
    }

    public function destroyRegency(Regency $regency): JsonResponse
    {
        $regency->delete();

        return ApiResponse::success(['id' => (string) $regency->id, 'removed' => true]);
    }

    public function districts(Request $request): JsonResponse
    {
        $query = District::query()->with('regency.province');

        $regency = trim((string) $request->input('regency', ''));
        if ($regency !== '') {
            $query->whereHas('regency', fn (Builder $query) => $query->where('name', $regency)->orWhere('code', $regency));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->limit(2000)->get()->map(fn (District $district) => $this->districtPayload($district))->values());
    }

    public function storeDistrict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'regency' => ['required', 'string', 'max:255'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $district = District::query()->create([
            'regency_id' => Regency::query()->where('name', $validated['regency'])->orWhere('code', $validated['regency'])->value('id'),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'postal_code' => $validated['postalCode'] ?? null,
        ]);

        return ApiResponse::success($this->districtPayload($district->load('regency.province')), [], 201);
    }

    public function destroyDistrict(District $district): JsonResponse
    {
        $district->delete();

        return ApiResponse::success(['id' => (string) $district->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function provincePayload(Province $province): array
    {
        return [
            'id' => (string) $province->id,
            'code' => $province->code,
            'name' => $province->name,
            'status' => $province->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function regencyPayload(Regency $regency): array
    {
        return [
            'id' => (string) $regency->id,
            'provinceId' => (string) $regency->province_id,
            'province' => $regency->province?->name,
            'code' => $regency->code,
            'name' => $regency->name,
            'type' => $regency->type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function districtPayload(District $district): array
    {
        return [
            'id' => (string) $district->id,
            'regencyId' => (string) $district->regency_id,
            'regency' => $district->regency?->name,
            'code' => $district->code,
            'name' => $district->name,
            'postalCode' => $district->postal_code,
        ];
    }
}
