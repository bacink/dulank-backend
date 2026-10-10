<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Invoice::query()->with(['order', 'user']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('invoice_no', 'like', $term)
                    ->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', $term));
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $invoices = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(InvoiceResource::collection($invoices), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function show(string $invoiceNo): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('invoice_no', $invoiceNo)
            ->with(['order', 'user', 'payments'])
            ->firstOrFail();

        return ApiResponse::success([
            'invoice' => new InvoiceResource($invoice),
            'payments' => PaymentResource::collection($invoice->payments),
        ]);
    }

    public function recordPayment(RecordPaymentRequest $request, string $invoiceNo, PaymentService $payments): JsonResponse
    {
        $invoice = Invoice::query()->where('invoice_no', $invoiceNo)->firstOrFail();
        $amount = $request->integer('amount');

        if ($amount > (int) $invoice->amount_due) {
            return ApiResponse::error('Nominal melebihi sisa tagihan.', 422, [
                'amount' => ['Nominal melebihi sisa tagihan (maksimal '.number_format((int) $invoice->amount_due, 0, ',', '.').').'],
            ]);
        }

        $payment = $payments->record(
            $request->user(),
            $invoice,
            $amount,
            (string) ($request->input('method') ?? 'Transfer'),
            $request->date('date')?->toDateString(),
            $request->input('notes'),
        );

        return ApiResponse::success(new PaymentResource($payment), [], 201);
    }
}
