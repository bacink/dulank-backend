<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Konversi quotation → order (PRD §7.6): menyalin item + snapshot customer,
 * menandai quotation Ordered, dan membuat invoice.
 */
final class QuotationService
{
    public function convertToOrder(Quotation $quotation, User $admin): Order
    {
        if (strtolower((string) $quotation->status) === 'ordered' && $quotation->order_id !== null) {
            throw ApiException::conflict('Quotation sudah dikonversi menjadi order.');
        }

        return DB::transaction(function () use ($quotation, $admin): Order {
            $quotation->loadMissing('items');

            $order = Order::query()->create([
                'number' => DocumentNumber::next('order', Order::query(), 'number', 3),
                'user_id' => $quotation->user_id,
                'customer_id' => $quotation->customer_id,
                'channel' => OrderChannel::Quotation,
                'status' => OrderStatus::AwaitingPayment,
                'shipping_address' => $quotation->document['shipping'] ?? null,
                'contact' => [
                    'name' => $quotation->customer_name,
                    'email' => $quotation->customer_email,
                    'phone' => $quotation->customer_phone,
                ],
                'sub_total' => (int) $quotation->sub_total,
                'discount' => (int) $quotation->discount,
                'tax' => (int) $quotation->tax,
                'delivery_fee' => (int) $quotation->shipping_cost,
                'total' => (int) $quotation->total,
                'paid' => 0,
                'due' => (int) $quotation->total,
                'payment_status' => PaymentStatus::Unpaid,
                'source_quotation_id' => $quotation->id,
                'ordered_at' => now(),
            ]);

            foreach ($quotation->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'category' => $item->category,
                    'quantity' => $item->order_qty,
                    'unit' => $item->unit,
                    'price' => (int) $item->unit_price,
                    'total' => (int) $item->amount,
                    'spec' => (string) $item->spec,
                    'artwork' => $item->artwork,
                    'print_result' => $item->print_result,
                    'note' => $item->note,
                ]);
            }

            $order->statusHistories()->createMany([
                ['status' => 'ordered', 'note' => 'Dibuat dari quotation '.$quotation->no_quotation, 'occurred_at' => now(), 'created_by' => $admin->id],
                ['status' => 'awaiting-payment', 'occurred_at' => now(), 'created_by' => $admin->id],
            ]);

            Invoice::query()->create([
                'invoice_no' => DocumentNumber::next('invoice', Invoice::query(), 'invoice_no', 3),
                'order_id' => $order->id,
                'user_id' => $quotation->user_id,
                'customer_id' => $quotation->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'amount' => (int) $quotation->total,
                'paid' => 0,
                'amount_due' => (int) $quotation->total,
                'status' => InvoiceStatus::Unpaid,
            ]);

            $quotation->forceFill([
                'status' => 'Ordered',
                'order_id' => $order->id,
            ])->save();

            return $order->load(['items', 'statusHistories']);
        });
    }
}
