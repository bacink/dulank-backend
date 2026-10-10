<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\QuotationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\AdminQuotationRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\QuotationResource;
use App\Models\Quotation;
use App\Services\QuotationService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Quotation::query()->with('items');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('no_quotation', 'like', $term)->orWhere('customer_name', 'like', $term);
            });
        }

        if ($request->filled('startDate')) {
            $query->whereDate('date', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('date', '<=', $request->date('endDate'));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $quotations = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(QuotationResource::collection($quotations), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function show(string $noQuotation): JsonResponse
    {
        $quotation = Quotation::query()->where('no_quotation', $noQuotation)->with('items')->firstOrFail();

        return ApiResponse::success(new QuotationResource($quotation));
    }

    public function store(AdminQuotationRequest $request): JsonResponse
    {
        $quotation = DB::transaction(function () use ($request): Quotation {
            $validated = $request->validated();
            $totals = $this->totals($validated);

            $quotation = Quotation::query()->create([
                'no_quotation' => DocumentNumber::next('quotation', Quotation::query(), 'no_quotation', 3),
                'date' => now()->toDateString(),
                'valid_until' => $request->date('dueDate')?->toDateString() ?? now()->addDays(30)->toDateString(),
                'status' => $validated['status'] ?? 'Pending',
                'channel' => QuotationChannel::fromAdmin((string) ($validated['channel'] ?? 'Offline')),
                'customer_name' => $validated['customer'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['telp'] ?? null,
                'sub_total' => $totals['subTotal'],
                'tax_rate' => $totals['taxRate'],
                'tax' => $totals['tax'],
                'shipping_cost' => $totals['shipping'],
                'total' => $totals['total'],
                'document' => $validated['document'] ?? null,
            ]);

            $this->syncItems($quotation, $validated['document']['items'] ?? null);

            return $quotation->load('items');
        });

        return ApiResponse::success(new QuotationResource($quotation), [], 201);
    }

    public function update(AdminQuotationRequest $request, string $noQuotation): JsonResponse
    {
        $quotation = Quotation::query()->where('no_quotation', $noQuotation)->firstOrFail();

        if ($quotation->order_id !== null) {
            return ApiResponse::error('Quotation yang sudah dikonversi tidak dapat diubah.', 422);
        }

        DB::transaction(function () use ($request, $quotation): void {
            $validated = $request->validated();
            $totals = $this->totals($validated);

            $quotation->forceFill([
                'valid_until' => $request->date('dueDate')?->toDateString() ?? $quotation->valid_until?->toDateString(),
                'status' => $validated['status'] ?? $quotation->status,
                'channel' => isset($validated['channel'])
                    ? QuotationChannel::fromAdmin((string) $validated['channel'])
                    : $quotation->channel,
                'customer_name' => $validated['customer'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['telp'] ?? null,
                'sub_total' => $totals['subTotal'],
                'tax_rate' => $totals['taxRate'],
                'tax' => $totals['tax'],
                'shipping_cost' => $totals['shipping'],
                'total' => $totals['total'],
                'document' => $validated['document'] ?? null,
            ])->save();

            if (array_key_exists('document', $validated)) {
                $this->syncItems($quotation, $validated['document']['items'] ?? null);
            }
        });

        return ApiResponse::success(new QuotationResource($quotation->refresh()->load('items')));
    }

    public function convert(Request $request, string $noQuotation, QuotationService $quotations): JsonResponse
    {
        $quotation = Quotation::query()->where('no_quotation', $noQuotation)->firstOrFail();

        $order = $quotations->convertToOrder($quotation, $request->user());

        return ApiResponse::success(new OrderResource($order), [], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{subTotal: int, shipping: int, taxRate: float, tax: int, total: int}
     */
    private function totals(array $validated): array
    {
        $document = (array) ($validated['document'] ?? []);
        $lines = (array) ($document['items'] ?? []);

        $subTotal = 0;
        foreach ($lines as $line) {
            $qty = (float) ($line['order'] ?? $line['quantity'] ?? 0);
            $price = (int) ($line['unitPrice'] ?? 0);
            $subTotal += (int) ($line['amount'] ?? round($price * $qty));
        }

        $shipping = (int) ($document['shippingCost'] ?? 0);
        $taxRate = (float) ($document['taxRate'] ?? 0);
        $tax = $subTotal > 0 ? (int) round($subTotal * $taxRate / 100) : 0;

        $total = (int) ($validated['total'] ?? 0);
        if ($total <= 0) {
            $total = $subTotal + $shipping + $tax;
        }

        return [
            'subTotal' => $subTotal ?: $total,
            'shipping' => $shipping,
            'taxRate' => $taxRate,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     */
    private function syncItems(Quotation $quotation, ?array $lines): void
    {
        if ($lines === null) {
            return;
        }

        $quotation->items()->delete();

        foreach ($lines as $line) {
            $qty = (float) ($line['order'] ?? $line['quantity'] ?? 0);
            $price = (int) ($line['unitPrice'] ?? 0);

            $quotation->items()->create([
                'product_id' => $line['productId'] ?? null,
                'product_name' => (string) ($line['productName'] ?? $line['description'] ?? 'Item'),
                'description' => $line['description'] ?? null,
                'moq' => $line['moq'] ?? null,
                'order_qty' => max($qty, 0.01),
                'unit' => $line['unit'] ?? null,
                'unit_price' => $price,
                'amount' => (int) ($line['amount'] ?? round($price * $qty)),
            ]);
        }
    }
}
