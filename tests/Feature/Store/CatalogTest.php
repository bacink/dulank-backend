<?php

use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Client;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Tag;

it('lists products with legacy contract keys and pagination meta', function () {
    $category = Category::factory()->create(['slug' => 'brosur']);
    $product = Product::factory()->for($category)->create([
        'name' => 'Brosur Full Color',
        'price_from' => 50000,
        'price_to' => 300000,
    ]);

    $product->specs()->create([
        'group_name' => 'Ukuran',
        'options' => ['A4', 'A5'],
        'sort_order' => 0,
    ]);

    $tag = Tag::query()->create(['name' => 'brosur', 'slug' => 'brosur']);
    $product->tags()->attach($tag);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.name', 'Brosur Full Color')
        ->assertJsonPath('data.0.categoryId', $category->id)
        ->assertJsonPath('data.0.priceFrom', 50000)
        ->assertJsonPath('data.0.specs.0.group', 'Ukuran')
        ->assertJsonPath('data.0.tags.0', 'brosur')
        ->assertJsonPath('data.0.isCalculator', false)
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'categoryId', 'description', 'priceFrom', 'priceTo', 'image', 'specs', 'tags']]]);
});

it('filters products by category slug, category id and search term', function () {
    $category = Category::factory()->create(['slug' => 'nota']);
    $match = Product::factory()->for($category)->create(['name' => 'Nota Custom']);
    Product::factory()->create(['name' => 'Produk Lain']);

    $this->getJson('/api/v1/products?category=nota')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $match->id);

    $this->getJson('/api/v1/products?category='.$category->id)->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/products?search=Nota')->assertJsonPath('meta.total', 1);
});

it('shows a product by slug and returns 404 for unknown slug', function () {
    $product = Product::factory()->create(['slug' => 'kartu-nama']);

    $this->getJson('/api/v1/products/kartu-nama')
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);

    $this->getJson('/api/v1/products/tidak-ada')
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

it('lists categories with computed product count', function () {
    $category = Category::factory()->create();
    Product::factory()->count(3)->for($category)->create();

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('data.0.id', $category->id)
        ->assertJsonPath('data.0.productCount', 3)
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'description', 'image', 'productCount']]]);
});

it('paginates blog posts with default limit 10', function () {
    BlogPost::factory()->count(12)->create();

    $response = $this->getJson('/api/v1/blog');

    $response->assertOk()
        ->assertJsonPath('meta.limit', 10)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonCount(10, 'data');

    $post = BlogPost::query()->first();

    $this->getJson('/api/v1/blog/'.$post->slug)
        ->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonStructure(['data' => ['id', 'slug', 'title', 'excerpt', 'content', 'date', 'author', 'image', 'tags']]);

    $this->getJson('/api/v1/blog/tidak-ada')->assertStatus(404);
});

it('lists faqs filtered by category name', function () {
    $category = FaqCategory::query()->create(['name' => 'pemesanan']);
    Faq::query()->create(['faq_category_id' => $category->id, 'question' => 'Q1', 'answer' => 'A1']);
    $other = FaqCategory::query()->create(['name' => 'pembayaran']);
    Faq::query()->create(['faq_category_id' => $other->id, 'question' => 'Q2', 'answer' => 'A2']);

    $this->getJson('/api/v1/faqs?category=pemesanan')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', 'pemesanan');
});

it('returns sale banners with legacy keys', function () {
    Banner::query()->create([
        'title' => 'Brosur Flyer',
        'discount' => 'Sale 20%',
        'image_url' => '/images/brosur.jpg',
        'type' => 'product',
        'status' => 'Active',
    ]);

    $this->getJson('/api/v1/sale-banners')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Brosur Flyer')
        ->assertJsonPath('data.0.discount', 'Sale 20%')
        ->assertJsonPath('data.0.image', '/images/brosur.jpg')
        ->assertJsonStructure(['data' => [['id', 'title', 'discount', 'image']]]);
});

it('serves catalog kinds via registry', function () {
    Client::query()->create(['name' => 'YouTube', 'logo_url' => '/images/clients/client-1.svg']);
    Machine::query()->create([
        'kind' => 'printing',
        'source' => 'Milik Sendiri',
        'name' => 'Heidelberg GTO52',
        'size' => '52x72 cm',
        'price' => 240000,
        'unit_price' => 60,
        'published' => true,
        'active' => true,
    ]);

    $this->getJson('/api/v1/catalog/clients')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'YouTube')
        ->assertJsonPath('data.0.logo', '/images/clients/client-1.svg')
        ->assertJsonStructure(['data' => [['id', 'name', 'logo']]]);

    $this->getJson('/api/v1/catalog/printing-machines')
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'printing')
        ->assertJsonPath('data.0.unitPrice', 60);

    $this->getJson('/api/v1/catalog/tidak-ada')->assertStatus(404);
});

it('serves the same payload through the /api compatibility alias', function () {
    Product::factory()->create();

    $v1 = $this->getJson('/api/v1/products')->json();
    $legacy = $this->getJson('/api/products')->json();

    expect($legacy['data'])->toEqual($v1['data']);
    expect($legacy['meta'])->toEqual($v1['meta']);
});
