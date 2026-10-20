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
use Illuminate\Support\Str;

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
        if ($id = $request->input('id')) {
            $province = Province::query()->findOrFail($id);
            return $this->updateProvince($request, $province);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'nullable', 'string', 'in:Active,Inactive'],
        ]);

        $province = Province::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('PRV-' . strtoupper(Str::random(4))),
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success($this->provincePayload($province), [], 201);
    }

    public function updateProvince(Request $request, Province $province): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'nullable', 'string', 'in:Active,Inactive'],
        ]);

        $province->forceFill([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $province->code,
            'status' => $validated['status'] ?? $province->status,
        ])->save();

        return ApiResponse::success($this->provincePayload($province));
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
        if ($id = $request->input('id')) {
            $regency = Regency::query()->findOrFail($id);
            return $this->updateRegency($request, $regency);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'province' => ['sometimes', 'string', 'max:255'],
            'province_id' => ['sometimes', 'nullable'],
            'type' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $provinceId = null;
        if (!empty($validated['province'])) {
            $foundProv = Province::query()->where('name', $validated['province'])->orWhere('code', $validated['province'])->first();
            $provinceId = $foundProv?->id;
        } elseif (!empty($validated['province_id'])) {
            $provinceId = $validated['province_id'];
        }

        if (!$provinceId) {
            $provinceId = Province::query()->value('id');
        }

        $regency = Regency::query()->create([
            'province_id' => $provinceId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('REG-' . strtoupper(Str::random(4))),
            'type' => $validated['type'] ?? (str_starts_with($validated['name'], 'Kota') ? 'Kota' : 'Kabupaten'),
        ]);

        return ApiResponse::success($this->regencyPayload($regency->load('province')), [], 201);
    }

    public function updateRegency(Request $request, Regency $regency): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'province' => ['sometimes', 'string', 'max:255'],
            'province_id' => ['sometimes', 'nullable'],
            'type' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $provinceId = $regency->province_id;
        if (!empty($validated['province'])) {
            $foundProv = Province::query()->where('name', $validated['province'])->orWhere('code', $validated['province'])->first();
            if ($foundProv) {
                $provinceId = $foundProv->id;
            }
        } elseif (!empty($validated['province_id'])) {
            $provinceId = $validated['province_id'];
        }

        $regency->forceFill([
            'province_id' => $provinceId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $regency->code,
            'type' => $validated['type'] ?? $regency->type,
        ])->save();

        return ApiResponse::success($this->regencyPayload($regency->load('province')));
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
        if ($id = $request->input('id')) {
            $district = District::query()->findOrFail($id);
            return $this->updateDistrict($request, $district);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'regency' => ['sometimes', 'string', 'max:255'],
            'regency_id' => ['sometimes', 'nullable'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $regencyId = null;
        if (!empty($validated['regency'])) {
            $foundReg = Regency::query()->where('name', $validated['regency'])->orWhere('code', $validated['regency'])->first();
            $regencyId = $foundReg?->id;
        } elseif (!empty($validated['regency_id'])) {
            $regencyId = $validated['regency_id'];
        }

        if (!$regencyId) {
            $regencyId = Regency::query()->value('id');
        }

        $district = District::query()->create([
            'regency_id' => $regencyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('DIS-' . strtoupper(Str::random(4))),
            'postal_code' => $validated['postalCode'] ?? null,
        ]);

        return ApiResponse::success($this->districtPayload($district->load('regency.province')), [], 201);
    }

    public function updateDistrict(Request $request, District $district): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'regency' => ['sometimes', 'string', 'max:255'],
            'regency_id' => ['sometimes', 'nullable'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $regencyId = $district->regency_id;
        if (!empty($validated['regency'])) {
            $foundReg = Regency::query()->where('name', $validated['regency'])->orWhere('code', $validated['regency'])->first();
            if ($foundReg) {
                $regencyId = $foundReg->id;
            }
        } elseif (!empty($validated['regency_id'])) {
            $regencyId = $validated['regency_id'];
        }

        $district->forceFill([
            'regency_id' => $regencyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $district->code,
            'postal_code' => $validated['postalCode'] ?? $district->postal_code,
        ])->save();

        return ApiResponse::success($this->districtPayload($district->load('regency.province')));
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
            'status' => $province->status ?? 'Active',
            'createdBy' => 'Admin',
            'added' => $province->created_at?->toDateString() ?? now()->toDateString(),
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
            'province' => $regency->province?->name ?? '',
            'code' => $regency->code,
            'name' => $regency->name,
            'type' => $regency->type,
            'status' => 'Active',
            'createdBy' => 'Admin',
            'added' => $regency->created_at?->toDateString() ?? now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function districtPayload(District $district): array
    {
        return [
            'id' => (string) $district->id,
            'provinceId' => (string) ($district->regency?->province_id ?? ''),
            'province' => (string) ($district->regency?->province?->name ?? ''),
            'regencyId' => (string) $district->regency_id,
            'regency' => (string) ($district->regency?->name ?? ''),
            'code' => $district->code,
            'name' => $district->name,
            'postalCode' => $district->postal_code,
            'status' => 'Active',
            'createdBy' => 'Admin',
            'added' => $district->created_at?->toDateString() ?? now()->toDateString(),
        ];
    }
}
