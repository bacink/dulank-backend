<?php

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;

it('manages blog posts, categories, tags and comments', function () {
    $token = adminToken(adminWithPermissions('content.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/blog-categories', ['name' => 'Tips'])->assertCreated();

    $created = $this->withToken($token)->postJson('/api/v1/admin/blogs', [
        'title' => 'Cara Cetak Brosur Profesional',
        'excerpt' => 'Panduan singkat',
        'content' => 'Isi artikel lengkap',
        'author' => 'Dulank Team',
        'category' => 'Tips',
        'tags' => ['cetak', 'brosur'],
        'status' => 'Publish',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.title', 'Cara Cetak Brosur Profesional')
        ->assertJsonPath('data.category', 'Tips')
        ->assertJsonPath('data.slug', 'cara-cetak-brosur-profesional')
        ->assertJsonPath('data.tags.0', 'cetak')
        ->assertJsonStructure(['data' => ['id', 'slug', 'title', 'excerpt', 'content', 'image', 'author', 'status', 'publishedAt']]);

    $this->withToken($token)->getJson('/api/v1/admin/blogs?status=Publish')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->postJson('/api/v1/admin/blog-tags', ['name' => 'offset'])->assertCreated();

    // Komentar moderation.
    $post = BlogPost::query()->firstOrFail();
    $comment = BlogComment::query()->create([
        'blog_post_id' => $post->id,
        'comment' => 'Mantap!',
        'rating' => 5,
        'author' => 'Pembaca',
        'status' => 'Publish',
    ]);

    $this->withToken($token)->getJson('/api/v1/admin/blog-comments')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->putJson("/api/v1/admin/blog-comments/{$comment->id}", ['status' => 'Unpublish'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Unpublish');
});

it('manages faqs and faq categories', function () {
    $token = adminToken(adminWithPermissions('content.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/faqs', [
        'question' => 'Berapa lama pengerjaan?',
        'answer' => '1-3 hari kerja.',
        'category' => 'pemesanan',
        'order' => 1,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.question', 'Berapa lama pengerjaan?');

    $this->withToken($token)->getJson('/api/v1/admin/faqs?category=pemesanan')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withToken($token)->getJson('/api/v1/admin/faq-categories')
        ->assertOk()
        ->assertJsonFragment(['name' => 'pemesanan', 'faqCount' => 1]);

    $id = $created->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/faqs/{$id}", [
        'question' => 'Berapa lama pengerjaan? (update)',
        'answer' => '1-2 hari kerja.',
    ])->assertOk()->assertJsonPath('data.question', 'Berapa lama pengerjaan? (update)');
});

it('manages clients, download files, footer and appearance', function () {
    $token = adminToken(adminWithPermissions('content.manage'));

    $this->withToken($token)->postJson('/api/v1/admin/clients', [
        'name' => 'YouTube',
        'logoUrl' => '/images/clients/client-1.svg',
    ])->assertCreated();

    $this->withToken($token)->getJson('/api/v1/admin/clients')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'YouTube');

    $this->withToken($token)->postJson('/api/v1/admin/download-files', [
        'name' => 'Katalog 2026',
    ])->assertCreated();

    $this->withToken($token)->putJson('/api/v1/admin/footer', [
        'infoKami' => 'PT Dulank Semesta Cida',
        'panduan' => ['Cara Order'],
    ])
        ->assertOk()
        ->assertJsonPath('data.infoKami', 'PT Dulank Semesta Cida')
        ->assertJsonPath('data.panduan.0', 'Cara Order');

    $this->withToken($token)->putJson('/api/v1/admin/appearance', [
        'theme' => 'light',
        'primaryColor' => '#0d6efd',
    ])
        ->assertOk()
        ->assertJsonPath('data.theme', 'light');
});

it('manages settings groups, custom fields, ban ips and languages', function () {
    $token = adminToken(adminWithPermissions('settings.manage'));

    $this->withToken($token)->putJson('/api/v1/admin/settings/company', [
        'name' => 'PT Dulank Semesta Cida',
        'email' => 'info@dulank.test',
    ])
        ->assertOk()
        ->assertJsonPath('data.values.name', 'PT Dulank Semesta Cida')
        ->assertJsonPath('data.values.email', 'info@dulank.test');

    $this->withToken($token)->getJson('/api/v1/admin/settings/company')
        ->assertOk()
        ->assertJsonPath('data.group', 'company');

    $this->withToken($token)->postJson('/api/v1/admin/custom-fields', [
        'module' => 'products',
        'label' => 'Berat',
        'name' => 'berat',
        'type' => 'number',
    ])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/admin/ban-ips', [
        'ipAddress' => '203.0.113.10',
        'reason' => 'Brute force',
    ])->assertCreated();

    $this->withToken($token)->getJson('/api/v1/admin/ban-ips')
        ->assertOk()
        ->assertJsonPath('data.0.ipAddress', '203.0.113.10');

    $language = $this->withToken($token)->postJson('/api/v1/admin/languages', [
        'code' => 'id',
        'name' => 'Bahasa Indonesia',
    ]);

    $language->assertCreated();

    $languageId = $language->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/languages/{$languageId}/translations", [
        'translations' => ['welcome' => 'Selamat datang', 'bye' => ''],
    ])->assertOk()
        ->assertJsonPath('data.translations.welcome', 'Selamat datang')
        ->assertJsonPath('data.progress', 50);
});

it('enforces content, settings and report permissions', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/blogs')->assertStatus(403);
    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/settings/company')->assertStatus(403);
    $this->withToken(adminToken($admin))->getJson('/api/v1/admin/dashboard')->assertStatus(403);

    $this->withToken(adminToken(adminWithPermissions('content.manage')))->getJson('/api/v1/admin/blogs')->assertOk();
});
