<?php

use App\Enums\CartItemStatus;
use App\Enums\DiscountType;
use App\Models\CartItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;

function checkoutToken(User $user): string
{
    return $user->createToken('checkout-test', ['store'])->plainTextToken;
}

function shippingPayload(): array
{
    return [
        'shippingAddress' => [
            'name' => 'Ahmad Dhani',
            'phone' => '+62852053769',
            'type' => 'Office',
            'street' => 'Jl. Arif Rahman Hakim',
            'district' => 'TELUK JAMBE TIMUR',
            'city' => 'KAB. KARAWANG',
            'province' => 'JAWA BARAT',
            'postalCode' => '41360',
            'country' => 'ID',
        ],
        'contact' => [
            'name' => 'Ahmad Dhani',
            'email' => 'ahmad@example.test',
            'phone' => '+62852053769',
        ],
    ];
}

it('checks out the active cart into an order with invoice and timeline', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price_from' => 50000, 'price_to' => 300000]);

    CartItem::factory()->for($user)->for($product)->create([
        'name' => $product->name,
        'quantity' => 2,
        'price' => 50000,
        'spec' => 'A4',
    ]);

    $response = $this->withToken(checkoutToken($user))
        ->postJson('/api/v1/orders', shippingPayload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'awaiting-payment')
        ->assertJsonPath('data.total', 111000) // 100.000 + PPN 11%
        ->assertJsonPath('data.items.0.name', $product->name)
        ->assertJsonPath('data.timeline.0.status', 'ordered')
        ->assertJsonPath('data.timeline.1.status', 'awaiting-payment');

    $order = Order::query()->firstOrFail();

    expect($order->number)->toStartWith('SO-')
        ->and($order->sub_total)->toBe(100000)
        ->and($order->tax)->toBe(11000)
        ->and($order->due)->toBe(111000)
        ->and($order->payment_status->value)->toBe('Unpaid')
        ->and($order->shipping_address['district'])->toBe('TELUK JAMBE TIMUR');

    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    expect($invoice->amount)->toBe(111000)
        ->and($invoice->amount_due)->toBe(111000)
        ->and($invoice->status->value)->toBe('Unpaid');

    // Keranjang dipindahkan: tidak ada item aktif lagi.
    $this->withToken(checkoutToken($user))->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'status' => CartItemStatus::Checkout->value]);
});

it('rejects checkout with an empty cart', function () {
    $user = User::factory()->create();

    $this->withToken(checkoutToken($user))
        ->postJson('/api/v1/orders', shippingPayload())
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['items']]);
});

it('requires a valid shipping address', function () {
    $user = User::factory()->create();

    $this->withToken(checkoutToken($user))
        ->postJson('/api/v1/orders', [])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['shippingAddress']]);
});

it('applies a percentage voucher and records usage', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price_from' => 100000]);

    CartItem::factory()->for($user)->for($product)->create(['quantity' => 1, 'price' => 100000]);

    $voucher = Voucher::query()->create([
        'name' => 'Diskon 10%',
        'code' => 'SAVE10',
        'type' => DiscountType::Percentage,
        'discount' => 10,
        'status' => 'Active',
    ]);

    $this->withToken(checkoutToken($user))
        ->postJson('/api/v1/orders', [...shippingPayload(), 'voucherCode' => 'SAVE10'])
        ->assertCreated()
        ->assertJsonPath('data.total', 99900); // (100.000 - 10.000) + 11% PPN

    expect(Order::query()->firstOrFail()->discount)->toBe(10000)
        ->and($voucher->fresh()->used)->toBe(1);
});

it('rejects an invalid voucher code', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    CartItem::factory()->for($user)->for($product)->create();

    $this->withToken(checkoutToken($user))
        ->postJson('/api/v1/orders', [...shippingPayload(), 'voucherCode' => 'NOPE'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['voucherCode']]);
});
