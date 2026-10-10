<?php

namespace App\Services;

use App\Enums\CartItemStatus;
use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\CartItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Checkout store: keranjang → order + timeline + invoice (PRD §7.3, §7.13).
 */
final class OrderService
{
    public function __construct(private readonly TaxService $tax) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkout(User $user, array $payload): Order
    {
        $cartItems = $this->resolveCartItems($user, $payload['items'] ?? null);

        return DB::transaction(function () use ($user, $payload, $cartItems): Order {
            $lines = $this->buildLines($cartItems, (array) ($payload['items'] ?? []));
            $subTotal = (int) collect($lines)->sum('total');

            [$discount, $voucher] = $this->resolveVoucher($payload['voucherCode'] ?? null, $subTotal);

            $taxBase = max($subTotal - $discount, 0);
            $tax = $this->tax->calculate($taxBase);
            $total = $taxBase + $tax;

            $order = Order::query()->create([
                'number' => DocumentNumber::next('order', Order::query(), 'number', 3),
                'user_id' => $user->id,
                'customer_id' => $user->customer?->id,
                'channel' => OrderChannel::Website,
                'status' => OrderStatus::AwaitingPayment,
                'shipping_method' => $payload['shippingMethod'] ?? null,
                'shipping_address' => $payload['shippingAddress'] ?? null,
                'contact' => $payload['contact'] ?? null,
                'sub_total' => $subTotal,
                'discount' => $discount,
                'tax' => $tax,
                'delivery_fee' => 0,
                'total' => $total,
                'paid' => 0,
                'due' => $total,
                'payment_status' => PaymentStatus::Unpaid,
                'notes' => $payload['notes'] ?? null,
                'voucher_code' => $voucher?->code,
                'ordered_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line['attributes']);
            }

            $order->statusHistories()->createMany([
                ['status' => 'ordered', 'occurred_at' => now(), 'created_by' => $user->id],
                ['status' => 'awaiting-payment', 'occurred_at' => now(), 'created_by' => $user->id],
            ]);

            Invoice::query()->create([
                'invoice_no' => DocumentNumber::next('invoice', Invoice::query(), 'invoice_no', 3),
                'order_id' => $order->id,
                'user_id' => $user->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'amount' => $total,
                'paid' => 0,
                'amount_due' => $total,
                'status' => InvoiceStatus::Unpaid,
            ]);

            CartItem::query()
                ->whereIn('id', $lines->pluck('cart_item_id'))
                ->update(['status' => CartItemStatus::Checkout]);

            return $order->load(['items', 'statusHistories', 'invoices']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     * @return Collection<int, CartItem>
     */
    private function resolveCartItems(User $user, ?array $items): Collection
    {
        $query = CartItem::query()
            ->where('user_id', $user->id)
            ->where('status', CartItemStatus::Active);

        if ($items !== null && $items !== []) {
            $ids = collect($items)->pluck('cartItemId')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $query->whereIn('id', $ids);
        }

        $cartItems = $query->get();

        if ($cartItems->isEmpty()) {
            throw ApiException::validation('Keranjang kosong atau item tidak ditemukan.', [
                'items' => ['Tidak ada item keranjang aktif untuk checkout.'],
            ]);
        }

        if ($items !== null && $items !== [] && $cartItems->count() !== collect($items)->pluck('cartItemId')->filter()->unique()->count()) {
            throw ApiException::validation('Sebagian item keranjang tidak ditemukan.', [
                'items' => ['Sebagian item keranjang tidak ditemukan atau sudah tidak aktif.'],
            ]);
        }

        return $cartItems;
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     * @param  array<int, array<string, mixed>>  $overrides
     * @return SupportCollection<int, array{cart_item_id: int, total: int, attributes: array<string, mixed>}>
     */
    private function buildLines(Collection $cartItems, array $overrides): SupportCollection
    {
        $byId = collect($overrides)->keyBy(fn (array $item) => (int) ($item['cartItemId'] ?? 0));

        return $cartItems->map(function (CartItem $cartItem) use ($byId): array {
            $override = (array) ($byId->get($cartItem->id) ?? []);
            $quantity = (float) $cartItem->quantity;
            $total = (int) round((int) $cartItem->price * $quantity);

            return [
                'cart_item_id' => $cartItem->id,
                'total' => $total,
                'attributes' => [
                    'product_id' => $cartItem->product_id,
                    'name' => $cartItem->name,
                    'category' => $cartItem->product?->category?->name,
                    'quantity' => $quantity,
                    'price' => (int) $cartItem->price,
                    'total' => $total,
                    'spec' => (string) $cartItem->spec,
                    'artwork' => $override['artwork'] ?? null,
                    'print_result' => $override['printResult'] ?? null,
                    'note' => $override['note'] ?? null,
                ],
            ];
        });
    }

    /**
     * Validasi dasar voucher (PRD §7.12) — pembatasan per-produk menyusul Fase 3.
     *
     * @return array{0: int, 1: Voucher|null}
     */
    private function resolveVoucher(?string $code, int $base): array
    {
        if ($code === null || trim($code) === '') {
            return [0, null];
        }

        $voucher = Voucher::query()->where('code', trim($code))->first();

        if ($voucher === null || $voucher->status !== 'Active') {
            throw ApiException::validation('Kode voucher tidak valid.', [
                'voucherCode' => ['Kode voucher tidak valid atau tidak aktif.'],
            ]);
        }

        $today = now()->startOfDay();

        if (($voucher->start_date !== null && $voucher->start_date->gt($today))
            || ($voucher->end_date !== null && $voucher->end_date->lt($today))) {
            throw ApiException::validation('Voucher tidak berlaku.', [
                'voucherCode' => ['Voucher di luar masa berlaku.'],
            ]);
        }

        if ($voucher->limit !== null && $voucher->used >= $voucher->limit) {
            throw ApiException::validation('Kuota voucher habis.', [
                'voucherCode' => ['Kuota voucher sudah habis.'],
            ]);
        }

        $discount = $voucher->type === DiscountType::Percentage
            ? (int) round($base * $voucher->discount / 100)
            : (int) $voucher->discount;

        $voucher->increment('used');

        return [min($discount, $base), $voucher];
    }
}
