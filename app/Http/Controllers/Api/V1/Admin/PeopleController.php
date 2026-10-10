<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountType;
use App\Enums\CustomerChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Address;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerType;
use App\Models\PostalCode;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Peoples: customers, customer-types, stores, addresses.
 */
class PeopleController extends Controller
{
    public function customers(AdminIndexRequest $request): JsonResponse
    {
        $query = Customer::query()->with('user');

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $query->whereHas('customerType', fn (Builder $query) => $query->where('name', $type));
        }

        $channel = trim((string) $request->input('channel', ''));
        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('code', 'like', $term)
                ->orWhereHas('user', fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $customers = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($customers->map(fn (Customer $customer) => $this->customerPayload($customer))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $validated = $this->validateCustomer($request);

        $customer = $this->saveCustomer($validated, null, $request);

        return ApiResponse::success($this->customerPayload($customer->load('user')), [], 201);
    }

    public function updateCustomer(Request $request, Customer $customer): JsonResponse
    {
        $validated = $this->validateCustomer($request);

        $customer = $this->saveCustomer($validated, $customer, $request);

        return ApiResponse::success($this->customerPayload($customer->refresh()->load('user')));
    }

    public function destroyCustomer(Customer $customer): JsonResponse
    {
        $customer->delete();

        return ApiResponse::success(['id' => (string) $customer->id, 'removed' => true]);
    }

    public function customerTypes(AdminIndexRequest $request): JsonResponse
    {
        $query = CustomerType::query()->withCount('customers');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (CustomerType $type) => [
            'id' => (string) $type->id,
            'name' => $type->name,
            'customerCount' => $type->customers_count,
            'status' => $type->status,
        ]));
    }

    public function storeCustomerType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $type = CustomerType::query()->firstOrCreate(['name' => $validated['name']], ['status' => $validated['status'] ?? 'Active']);

        return ApiResponse::success(['id' => (string) $type->id, 'name' => $type->name], [], 201);
    }

    public function destroyCustomerType(CustomerType $customerType): JsonResponse
    {
        $customerType->delete();

        return ApiResponse::success(['id' => (string) $customerType->id, 'removed' => true]);
    }

    public function stores(AdminIndexRequest $request): JsonResponse
    {
        $query = Store::query()->with('manager');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('store_name', 'like', $term)
                ->orWhere('email', 'like', $term));
        }

        return ApiResponse::success($query->orderBy('store_name')->get()->map(fn (Store $store) => [
            'id' => (string) $store->id,
            'storeName' => $store->store_name,
            'userName' => $store->manager?->name,
            'address' => $store->address,
            'phone' => $store->phone,
            'email' => $store->email,
            'status' => $store->status,
        ]));
    }

    public function storeStore(Request $request): JsonResponse
    {
        $validated = $this->validateStore($request);

        $store = Store::query()->create($this->storeAttributes($validated, null));

        return ApiResponse::success(['id' => (string) $store->id, 'storeName' => $store->store_name], [], 201);
    }

    public function updateStore(Request $request, Store $store): JsonResponse
    {
        $validated = $this->validateStore($request);

        $store->forceFill($this->storeAttributes($validated, $store))->save();

        return ApiResponse::success(['id' => (string) $store->id, 'storeName' => $store->store_name]);
    }

    public function destroyStore(Store $store): JsonResponse
    {
        $store->delete();

        return ApiResponse::success(['id' => (string) $store->id, 'removed' => true]);
    }

    public function addresses(AdminIndexRequest $request): JsonResponse
    {
        $query = Address::query();

        $ownerType = trim((string) $request->input('type', ''));
        if ($ownerType === 'customer') {
            $query->where('addressable_type', Customer::class);
        } elseif ($ownerType === 'supplier') {
            $query->where('addressable_type', Supplier::class);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('recipient_name', 'like', $term)
                ->orWhere('city', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $addresses = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($addresses->map(fn (Address $address) => $this->addressPayload($address))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $this->validateAddress($request);

        $address = Address::query()->create($this->addressAttributes($validated));

        return ApiResponse::success($this->addressPayload($address), [], 201);
    }

    public function updateAddress(Request $request, Address $address): JsonResponse
    {
        $validated = $this->validateAddress($request);

        $address->forceFill($this->addressAttributes($validated))->save();

        return ApiResponse::success($this->addressPayload($address->refresh()));
    }

    public function destroyAddress(Address $address): JsonResponse
    {
        $address->delete();

        return ApiResponse::success(['id' => (string) $address->id, 'removed' => true]);
    }

    public function addressStats(): JsonResponse
    {
        return ApiResponse::success([
            'totalAddress' => Address::query()->count(),
            'totalProvince' => Address::query()->whereNotNull('province')->distinct()->count('province'),
            'totalCity' => Address::query()->whereNotNull('city')->distinct()->count('city'),
            'totalPosCode' => PostalCode::query()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCustomer(Request $request): array
    {
        return $request->validate([
            'customerId' => ['sometimes', 'nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'channel' => ['sometimes', 'nullable', 'string', 'max:40'],
            'dateJoin' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive,Suspended'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveCustomer(array $validated, ?Customer $customer, Request $request): Customer
    {
        $user = $customer?->user
            ?? User::query()->where('email', $validated['email'])->first()
            ?? new User;

        if (! $user->exists) {
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => 'password',
                'type' => AccountType::Store,
                'join_date' => now(),
                'status' => 'Active',
            ])->save();
        }

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? $user->phone,
            'status' => ($validated['status'] ?? 'Active') === 'Active' ? 'Active' : 'Inactive',
        ])->save();

        $type = null;
        if (! empty($validated['type'])) {
            $type = CustomerType::query()->firstOrCreate(['name' => $validated['type']]);
        }

        $attributes = [
            'user_id' => $user->id,
            'code' => $validated['customerId'] ?? $customer?->code ?? 'CUS-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'customer_type_id' => $type?->id ?? $customer?->customer_type_id,
            'channel' => strtolower((string) ($validated['channel'] ?? 'Online')) === 'offline'
                ? CustomerChannel::Offline
                : CustomerChannel::Online,
            'date_join' => $request->date('dateJoin')?->toDateString() ?? $customer?->date_join?->toDateString() ?? now()->toDateString(),
            'last_seen' => now()->toDateString(),
        ];

        if ($customer === null) {
            $customer = Customer::query()->create($attributes);
        } else {
            $customer->forceFill($attributes)->save();
        }

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(Customer $customer): array
    {
        $entries = CustomerAccountEntry::query()->where('customer_id', $customer->id);
        $balance = (int) $entries->clone()->whereIn('source_type', ['opening_balance', 'sale', 'adjustment'])->sum('amount')
            - (int) $entries->clone()->where('source_type', 'payment')->sum('amount');

        return [
            'id' => (string) $customer->id,
            'customerId' => $customer->code,
            'name' => $customer->user?->name,
            'email' => $customer->user?->email,
            'type' => $customer->customerType?->name,
            'phone' => $customer->user?->phone,
            'channel' => $customer->channel?->value,
            'dateJoin' => $customer->date_join?->format('d/m/Y, H:i'),
            'lastSeen' => $customer->last_seen?->format('d/m/Y, H:i'),
            'status' => ($customer->user?->status ?? 'Active') === 'Active' ? 'Active' : 'Inactive',
            'balance' => $balance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStore(Request $request): array
    {
        return $request->validate([
            'storeName' => ['required', 'string', 'max:255'],
            'userName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function storeAttributes(array $validated, ?Store $store): array
    {
        $managerId = $store?->user_id;
        if (! empty($validated['userName'])) {
            $managerId = User::query()->where('name', $validated['userName'])->value('id') ?? $managerId;
        }

        return [
            'store_name' => $validated['storeName'],
            'user_id' => $managerId,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? $store?->status ?? 'Active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'ownerType' => ['required', 'in:customer,supplier'],
            'ownerId' => ['required', 'integer'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:30'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'detailAddress' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fullAddress' => ['sometimes', 'nullable', 'string', 'max:500'],
            'otherDetail' => ['sometimes', 'nullable', 'string', 'max:500'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'country' => ['sometimes', 'nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function addressAttributes(array $validated): array
    {
        return [
            'addressable_type' => $validated['ownerType'] === 'supplier' ? Supplier::class : Customer::class,
            'addressable_id' => (int) $validated['ownerId'],
            'label' => $validated['ownerType'],
            'recipient_name' => $validated['name'] ?? null,
            'phone' => $validated['contact'] ?? $validated['phone'] ?? null,
            'type' => $validated['status'] ?? 'Active',
            'name' => $validated['otherDetail'] ?? $validated['name'] ?? null,
            'street' => $validated['fullAddress'] ?? $validated['detailAddress'] ?? null,
            'district' => $validated['district'] ?? null,
            'city' => $validated['city'] ?? null,
            'province' => $validated['province'] ?? null,
            'postal_code' => $validated['postalCode'] ?? null,
            'country' => $validated['country'] ?? 'ID',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(Address $address): array
    {
        $ownerType = $address->addressable_type === Supplier::class || str_contains((string) $address->addressable_type, 'Supplier')
            ? 'supplier'
            : 'customer';

        return [
            'id' => (string) $address->id,
            'ownerType' => $ownerType,
            'ownerId' => (string) $address->addressable_id,
            'customerId' => $ownerType === 'customer' ? (string) $address->addressable_id : null,
            'userId' => $ownerType === 'supplier' ? (string) $address->addressable_id : null,
            'name' => $address->recipient_name,
            'contact' => $address->phone,
            'phone' => $address->phone,
            'province' => $address->province,
            'city' => $address->city,
            'district' => $address->district,
            'detailAddress' => $address->street,
            'fullAddress' => $address->street,
            'otherDetail' => $address->name,
            'postalCode' => $address->postal_code,
            'country' => $address->country,
            'status' => 'Active',
            'date' => $address->created_at?->toDateString(),
            'dateAdded' => $address->created_at?->toDateString(),
        ];
    }
}
