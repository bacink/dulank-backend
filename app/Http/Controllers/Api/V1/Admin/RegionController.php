<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\PostalCode;
use App\Models\Province;
use App\Models\Regency;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wilayah (provinsi/kabupaten/kecamatan) untuk dropdown alamat.
 */
class RegionController extends Controller
{
    public function options(): JsonResponse
    {
        return ApiResponse::success([
            'provinces' => Province::query()->where('status', 'Active')->orderBy('name')->get()->map(fn (Province $province) => $this->provincePayload($province)),
            'regencies' => Regency::query()->with('province')->where('status', 'Active')->whereHas('province', fn (Builder $query) => $query->where('status', 'Active'))->orderBy('name')->get()->map(fn (Regency $regency) => $this->regencyPayload($regency)),
            'districts' => District::query()->with('regency.province')->where('status', 'Active')->whereHas('regency', fn (Builder $query) => $query->where('status', 'Active')->whereHas('province', fn (Builder $query) => $query->where('status', 'Active')))->orderBy('name')->get()->map(fn (District $district) => $this->districtPayload($district)),
        ]);
    }

    public function postalCodes(Request $request): JsonResponse
    {
        $request->validate(['districtId' => ['sometimes', 'integer', 'exists:districts,id'], 'search' => ['sometimes', 'string', 'max:100']]);
        $query = PostalCode::query();
        if ($request->filled('districtId')) {
            $query->where('district_id', $request->input('districtId'));
        }
        if ($request->filled('search')) {
            $query->where('postal_code', 'like', $request->input('search').'%');
        }

        return ApiResponse::success($query->orderBy('postal_code')->orderBy('id')->limit(200)->get()->map(fn ($row) => ['id' => (string) $row->id, 'postalCode' => $row->postal_code, 'villageName' => $row->village_name, 'districtId' => $row->district_id ? (string) $row->district_id : null]));
    }

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

        MasterDataService::unique('provinces', 'name', $validated['name'], 'name', null, []);
        MasterDataService::unique('provinces', 'code', $validated['code'] ?? null, 'code', null);
        $province = Province::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('PRV-'.strtoupper(Str::random(4))),
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

        MasterDataService::unique('provinces', 'name', $validated['name'], 'name', $province->id, []);
        MasterDataService::unique('provinces', 'code', $validated['code'] ?? null, 'code', $province->id);
        $province->forceFill([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $province->code,
            'status' => $validated['status'] ?? $province->status,
        ])->save();

        return ApiResponse::success($this->provincePayload($province));
    }

    public function destroyProvince(Province $province): JsonResponse
    {
        MasterDataService::assertDeletable($province);
        $province->delete();

        return ApiResponse::success(['id' => (string) $province->id, 'removed' => true]);
    }

    public function regencies(Request $request): JsonResponse
    {
        $query = Regency::query()->with('province');
        if ($request->filled('provinceId')) {
            $query->where('province_id', $request->input('provinceId'));
        }

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
            'provinceId' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'province_id' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'status' => ['sometimes', 'in:Active,Inactive'],
            'type' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $provinceId = MasterDataService::resolve(Province::class, $validated['provinceId'] ?? $validated['province_id'] ?? null, $validated['province'] ?? null, 'provinceId');
        if ($provinceId === null) {
            throw ValidationException::withMessages(['provinceId' => ['Provinsi wajib dipilih.']]);
        }

        MasterDataService::unique('regencies', 'name', $validated['name'], 'name', null, ['province_id' => $provinceId]);
        MasterDataService::unique('regencies', 'code', $validated['code'] ?? null, 'code', null);
        $regency = Regency::query()->create([
            'province_id' => $provinceId,
            'status' => $validated['status'] ?? 'Active',
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('REG-'.strtoupper(Str::random(4))),
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
            'provinceId' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'province_id' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'status' => ['sometimes', 'in:Active,Inactive'],
            'type' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $provinceId = MasterDataService::resolve(Province::class, $validated['provinceId'] ?? $validated['province_id'] ?? null, $validated['province'] ?? null, 'provinceId') ?? $regency->province_id;

        MasterDataService::unique('regencies', 'name', $validated['name'], 'name', $regency->id, ['province_id' => $provinceId]);
        MasterDataService::unique('regencies', 'code', $validated['code'] ?? null, 'code', $regency->id);
        $regency->forceFill([
            'province_id' => $provinceId,
            'status' => $validated['status'] ?? $regency->status,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $regency->code,
            'type' => $validated['type'] ?? $regency->type,
        ])->save();

        return ApiResponse::success($this->regencyPayload($regency->load('province')));
    }

    public function destroyRegency(Regency $regency): JsonResponse
    {
        MasterDataService::assertDeletable($regency);
        $regency->delete();

        return ApiResponse::success(['id' => (string) $regency->id, 'removed' => true]);
    }

    public function districts(Request $request): JsonResponse
    {
        $query = District::query()->with('regency.province');
        if ($request->filled('regencyId')) {
            $query->where('regency_id', $request->input('regencyId'));
        }

        $regency = trim((string) $request->input('regency', ''));
        if ($regency !== '') {
            $query->whereHas('regency', fn (Builder $query) => $query->where('name', $regency)->orWhere('code', $regency));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (District $district) => $this->districtPayload($district))->values());
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
            'regencyId' => ['sometimes', 'nullable', 'integer', 'exists:regencies,id'],
            'regency_id' => ['sometimes', 'nullable', 'integer', 'exists:regencies,id'],
            'status' => ['sometimes', 'in:Active,Inactive'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $regencyId = MasterDataService::resolve(Regency::class, $validated['regencyId'] ?? $validated['regency_id'] ?? null, $validated['regency'] ?? null, 'regencyId');
        if ($regencyId === null) {
            throw ValidationException::withMessages(['regencyId' => ['Kabupaten/kota wajib dipilih.']]);
        }

        MasterDataService::unique('districts', 'name', $validated['name'], 'name', null, ['regency_id' => $regencyId]);
        MasterDataService::unique('districts', 'code', $validated['code'] ?? null, 'code', null);
        $district = District::query()->create([
            'regency_id' => $regencyId,
            'status' => $validated['status'] ?? 'Active',
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('DIS-'.strtoupper(Str::random(4))),
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
            'regencyId' => ['sometimes', 'nullable', 'integer', 'exists:regencies,id'],
            'regency_id' => ['sometimes', 'nullable', 'integer', 'exists:regencies,id'],
            'status' => ['sometimes', 'in:Active,Inactive'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $regencyId = MasterDataService::resolve(Regency::class, $validated['regencyId'] ?? $validated['regency_id'] ?? null, $validated['regency'] ?? null, 'regencyId') ?? $district->regency_id;

        MasterDataService::unique('districts', 'name', $validated['name'], 'name', $district->id, ['regency_id' => $regencyId]);
        MasterDataService::unique('districts', 'code', $validated['code'] ?? null, 'code', $district->id);
        $district->forceFill([
            'regency_id' => $regencyId,
            'status' => $validated['status'] ?? $district->status,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $district->code,
            'postal_code' => $validated['postalCode'] ?? $district->postal_code,
        ])->save();

        return ApiResponse::success($this->districtPayload($district->load('regency.province')));
    }

    public function destroyDistrict(District $district): JsonResponse
    {
        MasterDataService::assertDeletable($district);
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
            'status' => $regency->status ?? 'Active',
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
            'status' => $district->status ?? 'Active',
            'createdBy' => 'Admin',
            'added' => $district->created_at?->toDateString() ?? now()->toDateString(),
        ];
    }
}
