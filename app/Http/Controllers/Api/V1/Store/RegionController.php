<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\RegionQueryRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Master wilayah Indonesia (read-only).
 */
class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        $provinces = Province::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Province $province) => [
                'id' => $province->id,
                'code' => $province->code,
                'name' => $province->name,
            ]);

        return ApiResponse::success($provinces);
    }

    public function regencies(RegionQueryRequest $request): JsonResponse
    {
        $query = Regency::query()->orderBy('code');

        $province = trim((string) $request->input('province', ''));
        if ($province !== '') {
            $query->where(function (Builder $query) use ($province): void {
                $query->where('province_id', (int) $province)
                    ->orWhere('code', $province)
                    ->orWhereHas('province', fn (Builder $query) => $query
                        ->where('code', $province)
                        ->orWhere('name', $province));
            });
        }

        $regencies = $query->get()->map(fn (Regency $regency) => [
            'id' => $regency->id,
            'provinceId' => $regency->province_id,
            'code' => $regency->code,
            'name' => $regency->name,
            'type' => $regency->type,
        ]);

        return ApiResponse::success($regencies);
    }

    public function districts(RegionQueryRequest $request): JsonResponse
    {
        $query = District::query()->orderBy('name');

        $regency = trim((string) $request->input('regency', ''));
        if ($regency !== '') {
            $query->where(function (Builder $query) use ($regency): void {
                $query->where('regency_id', (int) $regency)
                    ->orWhere('code', $regency)
                    ->orWhereHas('regency', fn (Builder $query) => $query
                        ->where('code', $regency)
                        ->orWhere('name', 'like', '%'.$regency.'%'));
            });
        }

        $districts = $query->get()->map(fn (District $district) => [
            'id' => $district->id,
            'regencyId' => $district->regency_id,
            'code' => $district->code,
            'name' => $district->name,
            'postalCode' => $district->postal_code,
        ]);

        return ApiResponse::success($districts);
    }
}
