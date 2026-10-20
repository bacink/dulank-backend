<?php

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\LegacyIdMap;
use App\Models\Product;
use App\Models\ProfitTier;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\WorkFlow;
use Database\Seeders\DemoMasterDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('seeds fictional connected masters without financial transactions or opening stock', function () {
    $this->seed(DemoMasterDataSeeder::class);

    expect(Customer::query()->count())->toBe(8)
        ->and(Supplier::query()->count())->toBe(4)
        ->and(Employee::query()->count())->toBe(6)
        ->and(Product::query()->count())->toBe(8)
        ->and(BankAccount::query()->sum('opening_balance'))->toBe(0)
        ->and(Product::query()->sum('quantity'))->toBe(0);
    $product = Product::query()->where('code', 'DEMO-PRD-002')->firstOrFail();
    expect($product->category)->not->toBeNull()
        ->and($product->subCategory->category_id)->toBe($product->category_id)
        ->and($product->unit)->not->toBeNull()
        ->and($product->store)->not->toBeNull()
        ->and($product->variants)->toHaveCount(2)
        ->and($product->price_from)->toBe(55000)
        ->and($product->price_to)->toBe(75000);
    $workflow = WorkFlow::query()->firstOrFail();
    expect($workflow->product)->not->toBeNull()->and($workflow->stepList)->toHaveCount(4);
    expect(Customer::query()->firstOrFail()->user->status)->toBe('Inactive');
    foreach (['orders', 'payments', 'invoices', 'purchases', 'bank_ledger_entries', 'customer_account_entries', 'payslips', 'job_orders'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

it('can rerun seeding without duplicate rows or overwriting existing and edited records', function () {
    $category = Category::factory()->create(['name' => 'Media Promosi', 'status' => 'Inactive']);
    $this->seed(DemoMasterDataSeeder::class);
    $before = [];
    foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), false) as $table) {
        $before[$table] = DB::table($table)->count();
    }
    $product = Product::query()->where('code', 'DEMO-PRD-001')->firstOrFail();
    $product->update(['name' => 'Nama yang sudah diperbaiki pengguna', 'price' => 2300]);
    $this->seed(DemoMasterDataSeeder::class);

    expect($category->refresh()->status)->toBe('Inactive')
        ->and($product->refresh()->name)->toBe('Nama yang sudah diperbaiki pengguna')
        ->and($product->price)->toBe(2300);
    foreach ($before as $table => $count) {
        $this->assertDatabaseCount($table, $count);
    }
});

it('does not insert overlapping demo profit tiers over existing pricing rules', function () {
    ProfitTier::query()->create(['min_qty' => 1, 'max_qty' => null, 'profit_pos_percent' => 12, 'profit_webstore_percent' => 10]);
    $this->seed(DemoMasterDataSeeder::class);
    $this->assertDatabaseCount('profit_tiers', 1);
    expect(ProfitTier::query()->first()->profit_pos_percent)->toEqual(12);
});

it('rejects demo seeding in production before writing any records', function () {
    $this->app->detectEnvironment(fn () => 'production');
    expect(fn () => (new DemoMasterDataSeeder)->run())->toThrow(RuntimeException::class, 'local/testing');
    $this->assertDatabaseCount('stores', 0);
});

it('rolls back the entire demo seed when its provenance map is invalid', function () {
    LegacyIdMap::query()->create(['source' => 'demo', 'entity' => 'customers', 'legacy_id' => 'customer-0', 'model_type' => Supplier::class, 'model_id' => 999]);
    expect(fn () => (new DemoMasterDataSeeder)->run())->toThrow(RuntimeException::class, 'type mismatch');
    $this->assertDatabaseCount('stores', 0);
    $this->assertDatabaseCount('users', 0);
});

it('audits counts and missing relations without modifying application records', function () {
    Product::factory()->create(['unit_id' => null, 'store_id' => null]);
    $before = Product::query()->first()->getAttributes();
    expect(Artisan::call('dulank:audit-data', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($report['readOnly'])->toBeTrue()
        ->and($report['counts']['products'])->toBe(1)
        ->and($report['summary']['errors'])->toBe(0)
        ->and(collect($report['issues'])->where('check', 'missing_link')->where('table', 'products')->where('field', 'store_id')->first()['count'])->toBe(1)
        ->and(Product::query()->first()->getAttributes())->toBe($before);
    $this->artisan('dulank:audit-data', ['--strict' => true])->assertFailed();
});

it('reports invalid polymorphic owners without exposing personal data', function () {
    DB::table('addresses')->insert(['addressable_type' => 'App\\Models\\Unknown', 'addressable_id' => 99, 'recipient_name' => 'Private Test Recipient']);
    expect(Artisan::call('dulank:audit-data', ['--json' => true]))->toBe(1);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    expect($report['summary']['errors'])->toBe(1)
        ->and($output)->not->toContain('Private Test Recipient');
});

it('accepts legacy product kinds and persists separate canonical product and pricing types', function (string $kind, string $pricing) {
    $token = adminToken(adminWithPermissions('products.manage'));
    $response = $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => 'Kontrak produk', 'priceType' => $kind, 'price' => 12500]);
    $response->assertCreated()->assertJsonPath('data.productType', $kind)->assertJsonPath('data.priceType', $pricing);
    $this->assertDatabaseHas('products', ['id' => $response->json('data.id'), 'product_type' => $kind, 'price_type' => $pricing]);
    $this->withToken($token)->getJson('/api/v1/admin/products?productType='.urlencode($kind))->assertOk()->assertJsonPath('meta.total', 1);
    $this->withToken($token)->getJson('/api/v1/admin/products?productType=NoSuchKind')->assertUnprocessable();
})->with([
    ['Single Product', 'Fixed'], ['Variable Product', 'Range'], ['Size Calculation', 'Calculator'],
    ['Minimum Qty or Price', 'Calculator'], ['Large Format', 'Calculator'], ['Offset Service Price', 'Calculator'],
]);

it('rejects a subcategory of a different parent with 422 and no partial product', function () {
    $token = adminToken(adminWithPermissions('products.manage'));
    $category = Category::factory()->create();
    $other = Category::factory()->create();
    $sub = SubCategory::query()->create(['category_id' => $other->id, 'name' => 'Other category']);
    $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => 'Invalid parent', 'categoryId' => $category->id, 'subCategoryId' => $sub->id])->assertUnprocessable()->assertJsonValidationErrors('subCategoryId');
    $this->assertDatabaseCount('products', 0);
});

it('resolves equally named subcategories within the specified parent', function () {
    $token = adminToken(adminWithPermissions('products.manage'));
    $one = Category::factory()->create(['name' => 'Pertama']);
    $two = Category::factory()->create(['name' => 'Kedua']);
    SubCategory::query()->create(['category_id' => $one->id, 'name' => 'Umum']);
    $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => 'Parent-aware', 'category' => $two->name, 'subCategory' => 'Umum'])->assertCreated()->assertJsonPath('data.categoryId', (string) $two->id);
    $product = Product::query()->firstOrFail();
    expect($product->subCategory->category_id)->toBe($two->id);
});

it('rejects duplicate product codes with 422 and preserves the existing record', function () {
    $product = Product::factory()->create(['code' => 'UNIQUE-001']);
    $token = adminToken(adminWithPermissions('products.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => 'Duplicate', 'code' => $product->code])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->assertDatabaseCount('products', 1);
});

it('rolls back when a demo code belongs to an existing untracked product', function () {
    $product = Product::factory()->create(['code' => 'DEMO-PRD-001', 'name' => 'Original catalog item']);
    expect(fn () => (new DemoMasterDataSeeder)->run())->toThrow(RuntimeException::class, 'untracked record');
    $this->assertDatabaseCount('stores', 0);
    $this->assertDatabaseCount('customers', 0);
    expect($product->refresh()->name)->toBe('Original catalog item');
});

it('keeps product pricing and kind unchanged when updating other fields', function () {
    $token = adminToken(adminWithPermissions('products.manage'));
    $response = $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => 'Banner uji', 'productType' => 'Large Format', 'priceType' => 'Calculator', 'price' => 25000]);
    $response->assertCreated();
    $this->withToken($token)->putJson('/api/v1/admin/products/'.$response->json('data.id'), ['name' => 'Banner revisi'])->assertOk()->assertJsonPath('data.productType', 'Large Format')->assertJsonPath('data.priceType', 'Calculator');
});

it('serves the seeded master data through the admin APIs with valid envelopes', function () {
    $this->seed(DemoMasterDataSeeder::class);
    $token = adminToken(adminWithPermissions('products.manage', 'peoples.manage', 'hrm.manage', 'finance.manage', 'workflow.manage', 'content.manage', 'sales.manage', 'purchases.manage'));
    foreach (['customers', 'suppliers', 'stores', 'addresses', 'employees', 'employee-salaries', 'bank-accounts', 'products', 'variants', 'purchase-items', 'paper-items', 'paper-groups', 'paper-prices', 'paper-sizes', 'printing-machines', 'workshop-services', 'flow-categories', 'flow-names', 'flow-templates', 'work-flows', 'blog-categories', 'discount-plans', 'coupons', 'vouchers', 'calculator/partners'] as $path) {
        $this->withToken($token)->getJson('/api/v1/admin/'.$path)->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data']);
    }
    $this->withToken($token)->getJson('/api/v1/admin/workshop-services?category=printing')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson('/api/v1/admin/products?productType=Large%20Format')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.priceType', 'Calculator');
});

it('rejects invalid or contradictory product classification with 422', function (array $classification) {
    $token = adminToken(adminWithPermissions('products.manage'));
    $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => 'Invalid classification'] + $classification)->assertUnprocessable();
    $this->assertDatabaseCount('products', 0);
})->with([
    'unknown product kind' => [['productType' => 'Unknown']],
    'unknown pricing method' => [['priceType' => 'Unknown']],
    'conflicting legacy classification' => [['priceType' => 'Large Format', 'productType' => 'Single Product']],
]);
