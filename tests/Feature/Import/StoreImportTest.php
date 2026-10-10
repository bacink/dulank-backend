<?php

use App\Enums\AccountType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function fixturePath(): string
{
    return base_path('tests/Fixtures/store');
}

it('imports store mock data idempotently and preserves legacy ids', function () {
    $this->artisan('dulank:import', [
        'source' => 'store',
        '--path' => fixturePath(),
        '--password' => 'secret123',
    ])->assertSuccessful();

    // User + customer + address
    $user = User::query()->find(7);
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('ahmad@example.test')
        ->and($user->isStore())->toBeTrue()
        ->and(Hash::check('secret123', $user->password))->toBeTrue();
    $this->assertDatabaseHas('customers', ['user_id' => 7]);
    $this->assertDatabaseHas('addresses', ['id' => 3, 'addressable_id' => 7]);

    // Catalog: category + product + specs + tags
    $product = Product::query()->find(5);
    expect($product)->not->toBeNull()
        ->and($product->category_id)->toBe(3)
        ->and($product->price_from)->toBe(50000);
    expect($product->specs()->count())->toBe(1)
        ->and($product->tags()->count())->toBe(2);
    $this->assertDatabaseHas('categories', ['id' => 3, 'slug' => 'brosur-digital']);

    // Commerce
    $this->assertDatabaseHas('cart_items', ['user_id' => 7, 'product_id' => 5]);
    $this->assertDatabaseHas('wishlists', ['id' => 1, 'expire_date' => '2025-06-25 00:00:00']);

    $order = Order::query()->where('number', 'SO-1')->first();
    expect($order)->not->toBeNull()
        ->and($order->status->legacy())->toBe('delivered');
    expect($order->items()->count())->toBe(1)
        ->and($order->statusHistories()->count())->toBe(2);

    $this->assertDatabaseHas('quotations', ['no_quotation' => 'QT-1', 'status' => 'accepted']);
    $this->assertDatabaseHas('support_tickets', ['ticket_no' => 'TK-001']);

    $invoice = Invoice::query()->where('invoice_no', '600449168')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->order_id)->toBe($order->id);

    // Catalog kalkulator
    $this->assertDatabaseHas('machines', ['id' => 2, 'source' => 'Milik Sendiri']);
    $this->assertDatabaseHas('paper_groups', ['id' => 1, 'merk' => 'Sinar Dunia']);
    $this->assertDatabaseHas('clients', ['id' => 1, 'logo_url' => '/images/clients/client-1.svg']);
    $this->assertDatabaseHas('banners', ['id' => 2, 'type' => 'product']);

    // Idempotensi: jalankan kedua kali, tidak boleh menggandakan.
    $this->artisan('dulank:import', [
        'source' => 'store',
        '--path' => fixturePath(),
        '--password' => 'secret123',
    ])->assertSuccessful();

    expect(Product::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(1)
        ->and(SupportTicket::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1);
});

it('does not persist anything on dry-run', function () {
    $this->artisan('dulank:import', [
        'source' => 'store',
        '--path' => fixturePath(),
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0);
});

it('does not hijack a non-store account occupying the same legacy id', function () {
    $admin = new User;
    $admin->forceFill([
        'id' => 7,
        'name' => 'Admin Collision',
        'email' => 'admin-collision@example.test',
        'password' => 'admin12345',
        'type' => AccountType::Admin,
        'status' => 'Active',
    ])->save();

    $this->artisan('dulank:import', [
        'source' => 'store',
        '--path' => fixturePath(),
        '--password' => 'secret123',
    ])->assertSuccessful();

    $admin->refresh();

    expect($admin->isAdmin())->toBeTrue()
        ->and(Hash::check('admin12345', $admin->password))->toBeTrue();

    $storeUser = User::query()->where('email', 'ahmad@example.test')->first();

    expect($storeUser)->not->toBeNull()
        ->and($storeUser->id)->not->toBe(7)
        ->and($storeUser->isStore())->toBeTrue();

    // Semua referensi userId legacy diarahkan ke akun store hasil relokasi.
    expect(Order::query()->where('number', 'SO-1')->value('user_id'))->toBe($storeUser->id)
        ->and(Product::query()->count())->toBe(1);
});

it('fails for the admin source until phase 3', function () {
    $this->artisan('dulank:import', ['source' => 'admin'])->assertFailed();
});
