<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentDecisionRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Shared\ListQueryRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function index(ListQueryRequest $request): JsonResponse
    {
        $query = Payment::query()->with(['payable', 'user']);

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $direction = in_array(strtolower($type), ['payment-out', 'out', 'outflow'], true) ? 'out' : 'in';
            $query->where('direction', $direction);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('ref_no', 'like', '%'.$search.'%');
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
        $payments = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(PaymentResource::collection($payments), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function verify(PaymentDecisionRequest $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        $verified = $payments->verify($payment, $request->user(), $request->input('note'));

        return ApiResponse::success(new PaymentResource($verified));
    }

    public function reject(PaymentDecisionRequest $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        $rejected = $payments->reject($payment, $request->user(), $request->input('note'));

        return ApiResponse::success(new PaymentResource($rejected));
    }

    /**
     * Pencatatan pembayaran langsung oleh admin (verified seketika).
     */
    public function store(RecordPaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $payable = $this->resolvePayable($request);
        $amount = $request->integer('amount');
        $due = $payable instanceof Invoice ? (int) $payable->amount_due : (int) $payable->due;

        if ($amount > $due) {
            return ApiResponse::error('Nominal melebihi sisa tagihan.', 422, [
                'amount' => ['Nominal melebihi sisa tagihan (maksimal '.number_format($due, 0, ',', '.').').'],
            ]);
        }

        $payment = $payments->record(
            $request->user(),
            $payable,
            $amount,
            (string) ($request->input('method') ?? 'Cash'),
            $request->date('date')?->toDateString(),
            $request->input('notes'),
        );

        return ApiResponse::success(new PaymentResource($payment), [], 201);
    }

    private function resolvePayable(RecordPaymentRequest $request): Invoice|Order
    {
        $type = (string) $request->input('payableType', 'invoice');
        $id = (string) $request->input('payableId');

        if ($type === 'order') {
            return Order::query()->where('number', $id)->firstOrFail();
        }

        return Invoice::query()->where('invoice_no', $id)->firstOrFail();
    }
}
