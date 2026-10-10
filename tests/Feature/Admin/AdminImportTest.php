<?php

use App\Models\Customer;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\PaperSize;
use App\Models\PaperType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RequestQuotation;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;

function adminFixturePath(): string
{
    return base_path('tests/Fixtures/admin');
}

it('imports admin master data and keeps it idempotent', function () {
    $this->artisan('dulank:import', [
        'source' => 'admin',
        '--path' => adminFixturePath(),
        '--password' => 'secret123',
    ])->assertSuccessful();

    // Customer + user store dibuat dari data admin.
    $user = User::query()->where('email', 'dafa@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->isStore())->toBeTrue();
    $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'code' => 'ID000002']);

    $this->assertDatabaseHas('suppliers', ['code' => 'ID0001', 'name' => 'PT Kertas Jaya Makmur']);
    $this->assertDatabaseHas('stores', ['store_name' => 'Kacetak Pusat Karawang Barat']);
    $this->assertDatabaseHas('purchase_items', ['code' => 'ITEM-006', 'name' => 'art paper']);

    $po = PurchaseOrder::query()->where('no_po', 'PO-000007')->first();
    expect($po)->not->toBeNull()
        ->and($po->items()->count())->toBe(1)
        ->and($po->supplier?->name)->toBe('PT Kertas Jaya Makmur');

    $pr = PurchaseReturn::query()->where('no_pr', 'PRT-0015')->first();
    expect($pr)->not->toBeNull()
        ->and($pr->items()->count())->toBe(1);

    expect(RequestQuotation::query()->where('no_request', 'RFQ-001')->exists())->toBeTrue();
    expect(PaperGroup::query()->where('name', 'HVS Putih')->exists())->toBeTrue();
    expect(PaperType::query()->where('name', 'Art Paper 150gr')->exists())->toBeTrue();
    expect(PaperPrice::query()->where('name', 'A4')->exists())->toBeTrue();
    expect(PaperSize::query()->where('name', 'A4')->exists())->toBeTrue();

    // Prefix yang dikenal di-override, yang tidak dikenal diabaikan.
    expect(Setting::query()->where('group', 'prefixes')->where('key', 'ticket')->value('value'))
        ->toBe(['prefix' => 'TKT']);

    $suppliers = Supplier::query()->count();
    $orders = PurchaseOrder::query()->count();

    // Jalankan kedua kali: tidak boleh menggandakan.
    $this->artisan('dulank:import', [
        'source' => 'admin',
        '--path' => adminFixturePath(),
        '--password' => 'secret123',
    ])->assertSuccessful();

    expect(Supplier::query()->count())->toBe($suppliers)
        ->and(PurchaseOrder::query()->count())->toBe($orders)
        ->and(Customer::query()->count())->toBe(1);
});

it('does not persist admin imports on dry-run', function () {
    $this->artisan('dulank:import', [
        'source' => 'admin',
        '--path' => adminFixturePath(),
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Supplier::query()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(0);
});
