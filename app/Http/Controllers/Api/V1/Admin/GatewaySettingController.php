<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings berbentuk daftar: payment-gateways & sms-gateways
 * (disimpan sebagai array JSON pada grup setting terkait).
 */
class GatewaySettingController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const GROUPS = [
        'payment-gateways' => 'payment_gateway',
        'sms-gateways' => 'sms_gateway',
    ];

    public function index(string $group): JsonResponse
    {
        $key = $this->groupKey($group);

        if ($key === null) {
            return ApiResponse::error('Grup gateway tidak dikenal.', 404);
        }

        return ApiResponse::success($this->items($key));
    }

    public function store(Request $request, string $group): JsonResponse
    {
        $key = $this->groupKey($group);

        if ($key === null) {
            return ApiResponse::error('Grup gateway tidak dikenal.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        $items = $this->items($key);
        $item = [
            'id' => 'gw-'.strtolower(bin2hex(random_bytes(4))),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'status' => $validated['status'] ?? 'Active',
            'config' => $validated['config'] ?? [],
        ];

        $items[] = $item;
        $this->save($key, $items);

        return ApiResponse::success($item, [], 201);
    }

    public function update(Request $request, string $group, string $itemId): JsonResponse
    {
        $key = $this->groupKey($group);

        if ($key === null) {
            return ApiResponse::error('Grup gateway tidak dikenal.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        $items = $this->items($key);
        $found = false;

        foreach ($items as $index => $item) {
            if (($item['id'] ?? null) === $itemId) {
                $items[$index] = [
                    'id' => $itemId,
                    'name' => $validated['name'],
                    'code' => $validated['code'] ?? ($item['code'] ?? null),
                    'status' => $validated['status'] ?? ($item['status'] ?? 'Active'),
                    'config' => $validated['config'] ?? ($item['config'] ?? []),
                ];
                $found = true;
                break;
            }
        }

        if (! $found) {
            return ApiResponse::error('Gateway tidak ditemukan.', 404);
        }

        $this->save($key, $items);

        return ApiResponse::success(['id' => $itemId, 'name' => $validated['name']]);
    }

    public function destroy(string $group, string $itemId): JsonResponse
    {
        $key = $this->groupKey($group);

        if ($key === null) {
            return ApiResponse::error('Grup gateway tidak dikenal.', 404);
        }

        $items = array_values(array_filter(
            $this->items($key),
            fn (array $item) => ($item['id'] ?? null) !== $itemId,
        ));

        $this->save($key, $items);

        return ApiResponse::success(['id' => $itemId, 'removed' => true]);
    }

    private function groupKey(string $group): ?string
    {
        return self::GROUPS[$group] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(string $key): array
    {
        $setting = Setting::query()->where('group', $key)->where('key', 'list')->first();

        return array_values((array) ($setting?->value ?? []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function save(string $key, array $items): void
    {
        Setting::query()->updateOrCreate(
            ['group' => $key, 'key' => 'list'],
            ['value' => $items],
        );
    }
}
