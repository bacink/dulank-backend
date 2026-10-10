<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\QuotationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreQuotationRequest;
use App\Http\Requests\Store\UpdateQuotationRequest;
use App\Http\Resources\Store\QuotationResource;
use App\Models\Quotation;
use App\Services\TaxService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quotations = Quotation::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(QuotationResource::collection($quotations));
    }

    public function show(Request $request, string $noQuotation): JsonResponse
    {
        $quotation = Quotation::query()
            ->where('no_quotation', $noQuotation)
            ->with('items')
            ->first();

        if ($quotation === null) {
            return ApiResponse::error('Quotation not found.', 404);
        }

        $this->authorize('view', $quotation);

        return ApiResponse::success(new QuotationResource($quotation));
    }

    public function store(StoreQuotationRequest $request, TaxService $tax): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $quotation = DB::transaction(function () use ($request, $user, $tax, $validated): Quotation {
            $subTotal = $this->subTotal($validated['items']);
            $shipping = (int) ($validated['shippingCost'] ?? 0);
            $taxAmount = $tax->calculate($subTotal);
            $customer = $validated['customerInfo'] ?? [];

            $quotation = Quotation::query()->create([
                'no_quotation' => DocumentNumber::next('quotation', Quotation::query(), 'no_quotation', 3),
                'user_id' => $user->id,
                'customer_id' => $user->customer?->id,
                'date' => now()->toDateString(),
                'valid_until' => $request->date('validUntil')?->toDateString() ?? now()->addDays(30)->toDateString(),
                'status' => 'Pending',
                'channel' => QuotationChannel::Online,
                'customer_name' => $customer['name'] ?? $user->name,
                'customer_email' => $customer['email'] ?? $user->email,
                'customer_phone' => $customer['phone'] ?? $user->phone,
                'sub_total' => $subTotal,
                'discount' => 0,
                'tax_rate' => $tax->defaultRate(),
                'tax' => $taxAmount,
                'shipping_cost' => $shipping,
                'total' => $subTotal + $taxAmount + $shipping,
                'document' => isset($validated['notes']) && $validated['notes'] !== null
                    ? ['notes' => $validated['notes']]
                    : null,
            ]);

            $this->replaceItems($quotation, $validated['items']);

            return $quotation->load('items');
        });

        return ApiResponse::success(new QuotationResource($quotation), [], 201);
    }

    public function update(UpdateQuotationRequest $request, string $noQuotation, TaxService $tax): JsonResponse
    {
        $quotation = Quotation::query()->where('no_quotation', $noQuotation)->first();

        if ($quotation === null) {
            return ApiResponse::error('Quotation not found.', 404);
        }

        $this->authorize('update', $quotation);

        if (! in_array(strtolower((string) $quotation->status), ['draft', 'pending'], true)) {
            return ApiResponse::error('Quotation tidak dapat diubah pada status ini.', 422);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $quotation, $tax, $validated): void {
            $subTotal = $this->subTotal($validated['items']);
            $shipping = (int) ($validated['shippingCost'] ?? 0);
            $taxAmount = $tax->calculate($subTotal);
            $customer = $validated['customerInfo'] ?? [];

            $quotation->forceFill([
                'valid_until' => $request->date('validUntil')?->toDateString() ?? $quotation->valid_until,
                'customer_name' => $customer['name'] ?? $quotation->customer_name,
                'customer_email' => $customer['email'] ?? $quotation->customer_email,
                'customer_phone' => $customer['phone'] ?? $quotation->customer_phone,
                'sub_total' => $subTotal,
                'tax_rate' => $tax->defaultRate(),
                'tax' => $taxAmount,
                'shipping_cost' => $shipping,
                'total' => $subTotal + $taxAmount + $shipping,
                'document' => array_key_exists('notes', $validated)
                    ? ['notes' => $validated['notes']]
                    : $quotation->document,
            ])->save();

            $this->replaceItems($quotation, $validated['items']);
        });

        return ApiResponse::success(new QuotationResource($quotation->load('items')));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function subTotal(array $items): int
    {
        return (int) collect($items)->sum(
            fn (array $item) => (int) $item['unitPrice'] * (int) $item['quantity'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach ($items as $item) {
            $quotation->items()->create([
                'product_id' => $item['productId'] ?? null,
                'product_name' => $item['name'],
                'order_qty' => (int) $item['quantity'],
                'unit_price' => (int) $item['unitPrice'],
                'amount' => (int) $item['unitPrice'] * (int) $item['quantity'],
                'spec' => (string) ($item['spec'] ?? ''),
                'category' => $item['category'] ?? null,
                'artwork' => $item['artwork'] ?? null,
                'print_result' => $item['printResult'] ?? null,
                'note' => $item['note'] ?? null,
            ]);
        }
    }
}
