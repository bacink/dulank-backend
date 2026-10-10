<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreDeliveryNoteRequest;
use App\Http\Requests\Admin\UpdateDeliveryNoteRequest;
use App\Http\Resources\Admin\DeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Order;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeliveryNoteController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = DeliveryNote::query()->with('items');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('dn_no', 'like', $term)
                    ->orWhere('customer', 'like', $term)
                    ->orWhere('no_sales', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $notes = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(DeliveryNoteResource::collection($notes), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreDeliveryNoteRequest $request): JsonResponse
    {
        $note = DB::transaction(function () use ($request): DeliveryNote {
            $validated = $request->validated();
            $order = $this->resolveOrder($validated['noSales'] ?? null);

            $note = DeliveryNote::query()->create([
                'dn_no' => DocumentNumber::next('delivery_note', DeliveryNote::query(), 'dn_no', 3),
                'order_id' => $order?->id,
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'customer' => $validated['customer'] ?? $order?->contact['name'] ?? null,
                'no_sales' => $order?->number ?? ($validated['noSales'] ?? null),
                'shipping_address' => $validated['shippingAddress'] ?? $this->shippingLine($order),
                'status' => $validated['status'] ?? 'Pending',
                'date_status' => now()->toDateString(),
                'po' => $validated['po'] ?? null,
                'shipping_by' => $validated['shippingBy'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'receive_by' => $validated['receiveBy'] ?? null,
                'security' => $validated['security'] ?? null,
                'driver' => $validated['driver'] ?? null,
                'issued_by' => $validated['issuedBy'] ?? $request->user()->name,
            ]);

            $this->syncItems($note, $validated['items'] ?? []);

            return $note->load('items');
        });

        return ApiResponse::success(new DeliveryNoteResource($note), [], 201);
    }

    public function show(string $dnNo): JsonResponse
    {
        $note = DeliveryNote::query()->where('dn_no', $dnNo)->with('items')->firstOrFail();

        return ApiResponse::success(new DeliveryNoteResource($note));
    }

    public function update(UpdateDeliveryNoteRequest $request, string $dnNo): JsonResponse
    {
        $note = DeliveryNote::query()->where('dn_no', $dnNo)->firstOrFail();

        DB::transaction(function () use ($request, $note): void {
            $validated = $request->validated();
            $order = array_key_exists('noSales', $validated) ? $this->resolveOrder($validated['noSales']) : $note->order;

            $note->forceFill([
                'order_id' => $order?->id ?? $note->order_id,
                'date' => $request->date('date')?->toDateString() ?? $note->date?->toDateString(),
                'customer' => $validated['customer'] ?? $note->customer,
                'no_sales' => $order?->number ?? $note->no_sales,
                'shipping_address' => $validated['shippingAddress'] ?? $note->shipping_address,
                'status' => $validated['status'] ?? $note->status,
                'date_status' => now()->toDateString(),
                'po' => $validated['po'] ?? $note->po,
                'shipping_by' => $validated['shippingBy'] ?? $note->shipping_by,
                'reference' => $validated['reference'] ?? $note->reference,
                'receive_by' => $validated['receiveBy'] ?? $note->receive_by,
                'security' => $validated['security'] ?? $note->security,
                'driver' => $validated['driver'] ?? $note->driver,
                'issued_by' => $validated['issuedBy'] ?? $note->issued_by,
            ])->save();

            if (array_key_exists('items', $validated)) {
                $this->syncItems($note, $validated['items'] ?? []);
            }
        });

        return ApiResponse::success(new DeliveryNoteResource($note->refresh()->load('items')));
    }

    public function destroy(string $dnNo): JsonResponse
    {
        $note = DeliveryNote::query()->where('dn_no', $dnNo)->firstOrFail();
        $note->delete();

        return ApiResponse::success(['id' => $dnNo, 'removed' => true]);
    }

    private function resolveOrder(?string $noSales): ?Order
    {
        if ($noSales === null || trim($noSales) === '') {
            return null;
        }

        return Order::query()->where('number', $noSales)->first();
    }

    private function shippingLine(?Order $order): ?string
    {
        if ($order === null || ! is_array($order->shipping_address)) {
            return null;
        }

        $address = $order->shipping_address;

        return implode(', ', array_filter([
            $address['street'] ?? null,
            $address['district'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
            $address['postalCode'] ?? null,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(DeliveryNote $note, array $items): void
    {
        $note->items()->delete();

        foreach ($items as $item) {
            $note->items()->create([
                'description' => (string) $item['description'],
                'qty' => (float) $item['qty'],
                'unit' => $item['unit'] ?? null,
                'packing_qty' => $item['packingQty'] ?? null,
                'weight' => $item['weight'] ?? null,
            ]);
        }
    }
}
