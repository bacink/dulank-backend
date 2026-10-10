<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreAddressRequest;
use App\Http\Requests\Store\UpdateAddressRequest;
use App\Http\Resources\Store\AddressResource;
use App\Models\Address;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(AddressResource::collection($addresses));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $isDefault = $request->boolean('isDefault') || $user->addresses()->doesntExist();

        $address = DB::transaction(function () use ($request, $user, $isDefault): Address {
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create($this->attributes($request->validated()) + ['is_default' => $isDefault]);
        });

        return ApiResponse::success(new AddressResource($address), [], 201);
    }

    public function update(UpdateAddressRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $address = $this->findAddress($request, $id);

        $isDefault = $request->has('isDefault') ? $request->boolean('isDefault') : $address->is_default;

        DB::transaction(function () use ($address, $user, $request, $isDefault): void {
            if ($isDefault) {
                $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->forceFill($this->attributes($request->validated()) + ['is_default' => $isDefault])->save();
        });

        return ApiResponse::success(new AddressResource($address->refresh()));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $address = $this->findAddress($request, $id);
        $wasDefault = $address->is_default;
        $addressableType = $address->addressable_type;
        $addressableId = $address->addressable_id;

        DB::transaction(function () use ($address, $wasDefault, $addressableType, $addressableId): void {
            $address->delete();

            if ($wasDefault) {
                Address::query()
                    ->where('addressable_type', $addressableType)
                    ->where('addressable_id', $addressableId)
                    ->orderBy('id')
                    ->first()
                    ?->forceFill(['is_default' => true])
                    ->save();
            }
        });

        return ApiResponse::success(['id' => (int) $id, 'removed' => true]);
    }

    private function findAddress(Request $request, string $id): Address
    {
        $address = $request->user()->addresses()->whereKey($id)->first();

        if ($address === null) {
            abort(404, 'Address not found.');
        }

        return $address;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'label' => $validated['label'] ?? null,
            'recipient_name' => $validated['recipientName'],
            'phone' => $validated['phone'],
            'type' => $validated['type'] ?? 'Home',
            'name' => $validated['name'],
            'street' => $validated['street'],
            'district' => $validated['district'] ?? null,
            'city' => $validated['city'],
            'province' => $validated['province'] ?? null,
            'postal_code' => $validated['postalCode'] ?? null,
            'country' => $validated['country'] ?? 'ID',
        ];
    }
}
