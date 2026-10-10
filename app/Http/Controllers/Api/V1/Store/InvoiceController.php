<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\InvoiceResource;
use App\Models\Invoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with('order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(InvoiceResource::collection($invoices));
    }

    public function show(Request $request, string $invoiceNo): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('invoice_no', $invoiceNo)
            ->with('order')
            ->first();

        if ($invoice === null) {
            return ApiResponse::error('Invoice not found.', 404);
        }

        $this->authorize('view', $invoice);

        return ApiResponse::success(new InvoiceResource($invoice));
    }
}
