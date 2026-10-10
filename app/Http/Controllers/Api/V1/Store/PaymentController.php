<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SubmitPaymentRequest;
use App\Http\Resources\Store\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->where('user_id', $request->user()->id)
            ->with('payable')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(PaymentResource::collection($payments));
    }

    public function store(SubmitPaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $payload = $request->validated();
        unset($payload['proof']);

        $payment = $payments->submit($request->user(), $payload, $request->file('proof'));

        return ApiResponse::success(new PaymentResource($payment), [], 201);
    }
}
