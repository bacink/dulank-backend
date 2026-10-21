<?php

use App\Models\Partner;
use App\Models\PartnerMetric;

it('lists each partner type even when a partner has no metric record', function (string $type) {
    $partner = Partner::query()->create(['name' => 'Partner tanpa metrik', 'type' => $type, 'status' => 'Active', 'join_date' => '2026-09-01', 'subscribed' => true]);
    Partner::query()->create(['name' => 'Jenis lain', 'type' => $type === 'paper_store' ? 'printing_shop' : 'paper_store']);
    $token = adminToken(adminWithPermissions('products.manage'));
    $this->withToken($token)->getJson('/api/v1/admin/calculator/partners?type='.$type)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $partner->id)->assertJsonPath('data.0.metrics', null)->assertJsonPath('data.0.joinDate', '2026-09-01')->assertJsonPath('data.0.subscribed', true);
    $this->assertDatabaseCount('partner_metrics', 0);
})->with(['printing_shop', 'paper_store']);

it('preserves nonzero partner metrics instead of replacing them with defaults', function () {
    $partner = Partner::query()->create(['name' => 'Partner dengan metrik', 'type' => 'printing_shop']);
    PartnerMetric::query()->create(['partner_id' => $partner->id, 'paper_count' => 5, 'printing_count' => 2]);
    $token = adminToken(adminWithPermissions('products.manage'));
    $this->withToken($token)->getJson('/api/v1/admin/calculator/partners?type=printing_shop')->assertOk()->assertJsonPath('data.0.metrics.paperCount', 5)->assertJsonPath('data.0.metrics.printingCount', 2);
});
