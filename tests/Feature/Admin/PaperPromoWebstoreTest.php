<?php

use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Wishlist;

it('manages paper groups, items, sizes and prices', function () {
    $token = adminToken(adminWithPermissions('products.manage'));

    $group = $this->withToken($token)->postJson('/api/v1/admin/paper-groups', [
        'name' => 'HVS Putih',
        'merk' => 'Paperone',
        'priceType' => 'Yes',
    ]);

    $group->assertCreated()->assertJsonPath('data.name', 'HVS Putih');
    $groupId = $group->json('data.id');

    $this->withToken($token)->postJson('/api/v1/admin/paper-items', [
        'groupId' => $groupId,
        'name' => 'HVS A4 80gr',
        'merk' => 'Paperone',
        'price' => 52000,
        'unitPrice' => 'Kg',
        'gsm' => 80,
        'stock' => 1500,
        'unitStock' => 'Lembar',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'HVS A4 80gr')
        ->assertJsonPath('data.gsm', 80);

    $this->withToken($token)->postJson('/api/v1/admin/paper-sizes', [
        'name' => 'Plano 65x100',
        'dimension' => '65 x 100',
        'length' => 65,
        'width' => 100,
        'unit' => 'cm',
    ])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/admin/paper-prices', [
        'nama' => 'HVS A4 80gr per rim',
        'group' => 'HVS Putih',
        'harga' => 50000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.harga', 50000);

    $this->withToken($token)->getJson('/api/v1/admin/paper-groups')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'HVS Putih')
        ->assertJsonPath('data.0.typeCount', 1);
});

it('manages printing machines and die cutting blades', function () {
    $token = adminToken(adminWithPermissions('products.manage'));

    $machine = $this->withToken($token)->postJson('/api/v1/admin/printing-machines', [
        'kind' => 'printing',
        'type' => 'Offset',
        'name' => 'Heidelberg GTO52',
        'colors' => 4,
        'maxArea' => '52x72 cm',
        'plateCost' => 240000,
        'minim' => 100,
        'druck' => 60,
        'published' => true,
    ]);

    $machine->assertCreated()
        ->assertJsonPath('data.name', 'Heidelberg GTO52')
        ->assertJsonPath('data.colors', 4)
        ->assertJsonPath('data.kind', 'printing');

    $machineId = $machine->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/printing-machines/{$machineId}", [
        'name' => 'Heidelberg GTO52 (Revisi)',
        'kind' => 'printing',
        'druck' => 75,
    ])->assertOk()->assertJsonPath('data.druck', 75);

    $this->withToken($token)->postJson('/api/v1/admin/die-cutting-blades', [
        'name' => 'Piso/Rel',
        'pricePerCm' => 25,
    ])->assertCreated()->assertJsonPath('data.pricePerCm', 25);

    $this->withToken($token)->getJson('/api/v1/admin/printing-machines?kind=printing')
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'printing');
});

it('manages promo: banners, coupons, vouchers, discount plans and discounts', function () {
    $token = adminToken(adminWithPermissions('sales.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/banners', [
        'title' => 'Promo Kemerdekaan',
        'imageUrl' => '/images/promo.jpg',
        'type' => 'product',
        'status' => 'Active',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Promo Kemerdekaan')
        ->assertJsonPath('data.imageUrl', '/images/promo.jpg');

    $this->withToken($token)->postJson('/api/v1/admin/coupons', [
        'name' => 'Diskon 10%',
        'code' => 'SAVE10',
        'type' => 'Percentage',
        'discount' => 10,
        'validUntil' => now()->addMonth()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'SAVE10');

    $this->withToken($token)->postJson('/api/v1/admin/vouchers', [
        'name' => 'Voucher Gratis Ongkir',
        'code' => 'FREEONGKIR',
        'type' => 'Fixed',
        'discount' => 25000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'FREEONGKIR');

    $plan = $this->withToken($token)->postJson('/api/v1/admin/discount-plans', [
        'name' => 'Member VIP',
        'customers' => ['VIP'],
    ]);

    $plan->assertCreated();
    $planId = $plan->json('data.id');

    $this->withToken($token)->postJson('/api/v1/admin/discounts', [
        'name' => 'Diskon VIP 5%',
        'planId' => $planId,
        'value' => 5,
        'type' => 'Percentage',
    ])
        ->assertCreated()
        ->assertJsonPath('data.plan', 'Member VIP');

    $this->withToken($token)->getJson('/api/v1/admin/vouchers?search=FREE')
        ->assertOk()
        ->assertJsonPath('meta', null);
});

it('monitors webstore: carts, checkouts, wishlist, reviews and contact forms', function () {
    $token = adminToken(adminWithPermissions('orders.manage'));

    $user = User::factory()->create(['name' => 'Ahmad Dhani']);
    $product = Product::factory()->create(['name' => 'Brosur A4']);

    CartItem::factory()->for($user)->for($product)->create(['name' => 'Brosur A4', 'price' => 50000]);
    $order = Order::factory()->for($user)->create(['contact' => ['name' => 'Ahmad Dhani'], 'ordered_at' => now()]);
    Wishlist::factory()->for($user)->for($product)->create(['name' => 'Brosur A4']);

    $review = Review::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_name' => 'Brosur A4',
        'user_email' => $user->email,
        'date' => now()->toDateString(),
        'rating' => 5,
        'title' => 'Mantap',
        'review' => 'Hasil bagus',
        'status' => 'Publish',
    ]);

    ContactMessage::query()->create([
        'name' => 'Rina',
        'email' => 'rina@example.test',
        'message' => 'Tanya harga',
        'date' => now()->toDateString(),
        'status' => 'Unread',
    ]);

    $this->withToken($token)->getJson('/api/v1/admin/webstore/carts')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.product', 'Brosur A4');

    $this->withToken($token)->getJson('/api/v1/admin/webstore/checkout')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.customer', 'Ahmad Dhani');

    $this->withToken($token)->getJson('/api/v1/admin/webstore/wishlist')
        ->assertOk()->assertJsonPath('meta.total', 1);

    $this->withToken($token)->getJson('/api/v1/admin/webstore/reviews/stats')
        ->assertOk()->assertJsonPath('data.totalPublish', 1);

    $this->withToken($token)->putJson("/api/v1/admin/webstore/reviews/{$review->id}", ['status' => 'Unpublish'])
        ->assertOk()->assertJsonPath('data.status', 'Unpublish');

    $this->withToken($token)->getJson('/api/v1/admin/webstore/contact-forms/stats')
        ->assertOk()->assertJsonPath('data.totalContact', 1);

    $this->withToken($token)->getJson('/api/v1/admin/webstore/contact-forms')
        ->assertOk()->assertJsonPath('data.0.name', 'Rina');
});

it('manages calculator components, profit tiers, partners and listings moderation', function () {
    $token = adminToken(adminWithPermissions('products.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/calculator-components/komponen-fiks', [
        'name' => 'Biaya Plat',
        'value' => 25000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.value', 25000);

    $this->withToken($token)->postJson('/api/v1/admin/calculator-components/komponen-minimum', [
        'name' => 'Minimum Cetak',
        'rate' => 500,
        'minim' => 100000,
        'unit' => 'lembar',
    ])
        ->assertCreated()
        ->assertJsonPath('data.minim', 100000);

    $this->withToken($token)->postJson('/api/v1/admin/calculator-components/jasa-lain', [
        'name' => 'Jasa Desain',
        'harga' => 150000,
        'minimHarga' => 100000,
        'satuan' => 'desain',
    ])
        ->assertCreated()
        ->assertJsonPath('data.harga', 150000);

    $this->withToken($token)->getJson('/api/v1/admin/calculator-components/komponen-minimum')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Minimum Cetak');

    $this->withToken($token)->postJson('/api/v1/admin/profit-tiers', [
        'minQty' => 1,
        'maxQty' => 100,
        'profitPosPercent' => 20,
        'profitWebstorePercent' => 30,
    ])->assertCreated();

    $partner = $this->withToken($token)->postJson('/api/v1/admin/calculator/partners', [
        'type' => 'printing_shop',
        'name' => 'Percetakan Grafika',
        'province' => 'DKI Jakarta',
        'city' => 'Jakarta Pusat',
    ]);

    $partner->assertCreated()->assertJsonPath('data.name', 'Percetakan Grafika');
    $partnerId = (int) $partner->json('data.id');

    $listing = Listing::query()->create([
        'partner_id' => $partnerId,
        'type' => 'machine',
        'payload' => ['name' => 'GTO52'],
        'status' => 'Active',
        'published' => true,
    ]);

    $this->withToken($token)->postJson("/api/v1/admin/calculator/listings/{$listing->id}/freeze", [
        'duration' => 7,
        'notify' => true,
        'message' => 'Data tidak valid',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Frozen')
        ->assertJsonPath('data.published', false);

    $this->withToken($token)->getJson('/api/v1/admin/calculator/moderation-history')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.action', 'freeze');

    $this->withToken($token)->getJson('/api/v1/admin/calculator/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data' => ['telemetry' => ['currentUsers', 'totalRequest', 'totalCalculate'], 'users']]);
});

it('updates a discount plan', function () {
    $token = adminToken(adminWithPermissions('sales.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/discount-plans', [
        'name' => 'Plan Smoke', 'status' => 'Active',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Plan Smoke');

    $id = $created->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/discount-plans/{$id}", [
        'name' => 'Plan Smoke Edit', 'customers' => ['VIP'], 'status' => 'Inactive',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Plan Smoke Edit')
        ->assertJsonPath('data.customers', ['VIP'])
        ->assertJsonPath('data.status', 'Inactive');

    $this->assertDatabaseHas('discount_plans', ['id' => $id, 'status' => 'Inactive']);
});

it('manages support tickets end to end', function () {
    $token = adminToken(adminWithPermissions('orders.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/webstore/support-tickets', [
        'subject' => 'Kertas sobek', 'requestedBy' => 'Budi', 'customerEmail' => 'budi@test.dev',
        'priority' => 'High',
    ])->assertCreated()
        ->assertJsonPath('data.subject', 'Kertas sobek')
        ->assertJsonPath('data.priority', 'High')
        ->assertJsonPath('data.status', 'Open');

    $id = $created->json('data.id');

    $this->withToken($token)->getJson('/api/v1/admin/webstore/support-tickets?search=Kertas')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure(['data' => [['id', 'ticketNo', 'subject', 'priority', 'status', 'assignee']]]);

    $this->withToken($token)->getJson('/api/v1/admin/webstore/support-tickets/stats')
        ->assertOk()
        ->assertJsonStructure(['data' => ['totalTickets', 'totalPendingTickets', 'totalClosedTickets']]);

    $this->withToken($token)->putJson("/api/v1/admin/webstore/support-tickets/{$id}", [
        'status' => 'Closed', 'priority' => 'Low',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Closed')
        ->assertJsonPath('data.priority', 'Low');

    $this->withToken($token)->getJson("/api/v1/admin/webstore/support-tickets/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'Closed');

    $this->withToken($token)->deleteJson("/api/v1/admin/webstore/support-tickets/{$id}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('support_tickets', ['id' => $id]);
});

it('moderates a calculator partner', function () {
    $token = adminToken(adminWithPermissions('products.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/calculator/partners', [
        'name' => 'Partner Smoke', 'type' => 'printing_shop', 'city' => 'Karawang', 'status' => 'Active',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Partner Smoke');

    $id = $created->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/calculator/partners/{$id}/moderate", [
        'status' => 'Inactive', 'note' => 'Menunggu verifikasi dokumen',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Inactive');

    $this->assertDatabaseHas('partners', ['id' => $id, 'status' => 'Inactive']);
});
