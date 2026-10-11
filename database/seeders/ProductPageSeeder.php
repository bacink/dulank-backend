<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Stores the storefront product-page config (spec options with prices,
 * price lists, design galleries, printing guides) into the settings table
 * so the Nuxt storefront can render them via the settings API.
 */
class ProductPageSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/product-pages.json');

        if (! File::exists($path)) {
            throw new RuntimeException("Missing {$path}");
        }

        $pages = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($pages as $slug => $page) {
            Setting::query()->updateOrCreate(
                ['group' => 'product-pages', 'key' => $slug],
                ['value' => $page],
            );
        }

        $this->command?->info('Imported '.count($pages).' product pages.');
    }
}