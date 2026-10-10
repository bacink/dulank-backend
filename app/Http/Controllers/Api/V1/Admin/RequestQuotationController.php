<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreRfqRequest;
use App\Http\Requests\Admin\UpdateRfqRequest;
use App\Http\Resources\Admin\RequestQuotationResource;
use App\Models\RequestQuotation;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class RequestQuotationController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = RequestQuotation::query();

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('no_request', 'like', $term)->orWhere('customer', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $rfqs = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(RequestQuotationResource::collection($rfqs), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreRfqRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $rfq = RequestQuotation::query()->create([
            'no_request' => DocumentNumber::next('request_quotation', RequestQuotation::query(), 'no_request', 3),
            'customer' => $validated['customer'],
            'email' => $validated['email'] ?? null,
            'telp' => $validated['telp'] ?? null,
            'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
            'status' => $validated['status'] ?? 'Pending',
            'due_date' => $request->date('dueDate')?->toDateString(),
            'payment_term' => $validated['paymentTerm'] ?? null,
            'document' => $validated['document'] ?? null,
        ]);

        return ApiResponse::success(new RequestQuotationResource($rfq), [], 201);
    }

    public function show(string $noRequest): JsonResponse
    {
        $rfq = RequestQuotation::query()->where('no_request', $noRequest)->firstOrFail();

        return ApiResponse::success(new RequestQuotationResource($rfq));
    }

    public function update(UpdateRfqRequest $request, string $noRequest): JsonResponse
    {
        $rfq = RequestQuotation::query()->where('no_request', $noRequest)->firstOrFail();
        $validated = $request->validated();

        $rfq->forceFill([
            'customer' => $validated['customer'],
            'email' => $validated['email'] ?? null,
            'telp' => $validated['telp'] ?? null,
            'date' => $request->date('date')?->toDateString() ?? $rfq->date?->toDateString(),
            'status' => $validated['status'] ?? $rfq->status,
            'due_date' => $request->date('dueDate')?->toDateString() ?? $rfq->due_date?->toDateString(),
            'payment_term' => $validated['paymentTerm'] ?? $rfq->payment_term,
            'document' => $validated['document'] ?? $rfq->document,
        ])->save();

        return ApiResponse::success(new RequestQuotationResource($rfq->refresh()));
    }

    public function destroy(string $noRequest): JsonResponse
    {
        $rfq = RequestQuotation::query()->where('no_request', $noRequest)->firstOrFail();
        $rfq->delete();

        return ApiResponse::success(['id' => $noRequest, 'removed' => true]);
    }

    /**
     * Duplikat RFQ: salin header + dokumen (termasuk recipients), nomor baru.
     */
    public function duplicate(string $noRequest): JsonResponse
    {
        $source = RequestQuotation::query()->where('no_request', $noRequest)->firstOrFail();

        $copy = $source->replicate(['no_request', 'created_at', 'updated_at']);
        $copy->no_request = DocumentNumber::next('request_quotation', RequestQuotation::query(), 'no_request', 3);
        $copy->date = now()->toDateString();
        $copy->status = 'Pending';
        $copy->save();

        return ApiResponse::success(new RequestQuotationResource($copy), [], 201);
    }
}
