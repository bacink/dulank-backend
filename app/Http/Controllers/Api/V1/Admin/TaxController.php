<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreInputTaxRequest;
use App\Http\Requests\Admin\StoreOutputTaxRequest;
use App\Http\Resources\Admin\InputTaxResource;
use App\Http\Resources\Admin\OutputTaxResource;
use App\Models\InputTaxDocument;
use App\Models\Order;
use App\Models\OutputTaxDocument;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaxController extends Controller
{
    public function inputIndex(AdminIndexRequest $request): JsonResponse
    {
        $query = InputTaxDocument::query()->with(['purchase.supplier']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('faktur_no', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $documents = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(InputTaxResource::collection($documents), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function inputStore(StoreInputTaxRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $document = InputTaxDocument::query()->create([
            'purchase_id' => $validated['purchaseId'] ?? null,
            'invoice_date' => $request->date('invoiceDate')?->toDateString() ?? now()->toDateString(),
            'faktur_no' => $validated['fakturNo'] ?? null,
            'credited' => $validated['credited'] ?? 'No',
        ]);

        return ApiResponse::success(new InputTaxResource($document->load('purchase.supplier')), [], 201);
    }

    public function inputUpdate(StoreInputTaxRequest $request, InputTaxDocument $inputTaxDocument): JsonResponse
    {
        $validated = $request->validated();

        $inputTaxDocument->forceFill([
            'purchase_id' => $validated['purchaseId'] ?? $inputTaxDocument->purchase_id,
            'invoice_date' => $request->date('invoiceDate')?->toDateString() ?? $inputTaxDocument->invoice_date?->toDateString(),
            'faktur_no' => $validated['fakturNo'] ?? null,
            'credited' => $validated['credited'] ?? $inputTaxDocument->credited,
        ])->save();

        return ApiResponse::success(new InputTaxResource($inputTaxDocument->refresh()->load('purchase.supplier')));
    }

    public function outputIndex(AdminIndexRequest $request): JsonResponse
    {
        $query = OutputTaxDocument::query()->with('order');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('etax_number', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $documents = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(OutputTaxResource::collection($documents), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function outputStore(StoreOutputTaxRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderId = null;

        if (! empty($validated['saleId'])) {
            $orderId = Order::query()->where('number', $validated['saleId'])->value('id');
        }

        $document = OutputTaxDocument::query()->create([
            'order_id' => $orderId,
            'etax_date' => $request->date('etaxDate')?->toDateString() ?? now()->toDateString(),
            'etax_number' => $validated['etaxNumber'] ?? null,
            'tx_code' => $validated['txCode'] ?? null,
            'status' => $validated['status'] ?? 'Draft',
        ]);

        return ApiResponse::success(new OutputTaxResource($document->load('order')), [], 201);
    }

    public function outputUpdate(StoreOutputTaxRequest $request, OutputTaxDocument $outputTaxDocument): JsonResponse
    {
        $validated = $request->validated();

        $orderId = $outputTaxDocument->order_id;
        if (array_key_exists('saleId', $validated)) {
            $orderId = $validated['saleId'] !== null
                ? Order::query()->where('number', $validated['saleId'])->value('id')
                : null;
        }

        $outputTaxDocument->forceFill([
            'order_id' => $orderId,
            'etax_date' => $request->date('etaxDate')?->toDateString() ?? $outputTaxDocument->etax_date?->toDateString(),
            'etax_number' => $validated['etaxNumber'] ?? null,
            'tx_code' => $validated['txCode'] ?? null,
            'status' => $validated['status'] ?? $outputTaxDocument->status,
        ])->save();

        return ApiResponse::success(new OutputTaxResource($outputTaxDocument->refresh()->load('order')));
    }
}
