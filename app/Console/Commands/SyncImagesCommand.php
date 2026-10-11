<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Client;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Salin & normalisasi gambar storefront ke disk `public` Laravel.
 *
 * - Menyalin file dari direktori sumber (default: dulank-store/public/images)
 *   ke storage/app/public/images (terbuka via symlink `/storage/images/...`).
 * - Mengunduh gambar yang hilang dari sumber eksternal (galeri dummy,
 *   placeholder banner/promo) ke folder yang sama.
 * - Mengganti prefix path pada seluruh data: `/images/x` → `/storage/images/x`
 *   (produk, kategori, banner, partner, client, blog, avatar user, cart/wishlist).
 * - Menormalkan settings: `appearance.featuredServices` dan group
 *   `product-pages` (galeri desain), serta menulis ulang
 *   `database/data/product-pages.json` agar re-seed konsisten.
 */
class SyncImagesCommand extends Command
{
    protected $signature = 'dulank:sync-images
        {--source= : Direktori sumber gambar (default: env DULANK_IMAGE_SOURCE)}';

    protected $description = 'Salin gambar frontend ke Laravel public storage dan normalisasi path-nya';

    private const GALLERY_BASE = 'https://skala.or.id/wp-content/uploads/2024/01/';

    public function handle(): int
    {
        $source = $this->option('source')
            ?? env('DULANK_IMAGE_SOURCE', '/home/bacink/DULANK/2026/dulank-store/public/images');

        if (! is_dir($source)) {
            throw new RuntimeException("Direktori sumber gambar tidak ditemukan: {$source}");
        }

        $this->copyFromSource($source);
        $this->downloadMissing();
        $this->normalizeDatabase();
        $this->normalizeSettings();
        $this->rewriteProductPagesJson();

        $this->info('Images synced ke public storage.');

        return self::SUCCESS;
    }

    private function copyFromSource(string $source): void
    {
        $target = Storage::disk('public')->path('images');

        foreach (File::files($source) as $file) {
            $name = $file->getFilename();
            $dest = "images/{$name}";
            if (! Storage::disk('public')->exists($dest)) {
                Storage::disk('public')->put($dest, $file->getContents());
                $this->line("  copied {$name}");
            }
        }

        $clientDir = rtrim($source, '/').'/clients';
        if (is_dir($clientDir)) {
            foreach (File::files($clientDir) as $file) {
                $dest = 'images/clients/'.$file->getFilename();
                if (! Storage::disk('public')->exists($dest)) {
                    Storage::disk('public')->put($dest, $file->getContents());
                    $this->line('  copied clients/'.$file->getFilename());
                }
            }
        }
    }

    private function downloadMissing(): void
    {
        $downloads = [
            // Galeri desain product pages (URL eksternal → storage lokal).
            'images/gallery/dummy-post-square-1-1.jpg' => self::GALLERY_BASE.'dummy-post-square-1-1.jpg',
        ];

        foreach ($downloads as $dest => $url) {
            if (Storage::disk('public')->exists($dest)) {
                continue;
            }
            $response = Http::timeout(20)->get($url);
            if ($response->successful()) {
                Storage::disk('public')->put($dest, $response->body());
                $this->line("  downloaded {$dest}");
            } else {
                $this->warn("  gagal mengunduh {$url}");
            }
        }
    }

    private function normalizeDatabase(): void
    {
        $maps = [
            [Product::class, 'image_path'],
            [Category::class, 'image_path'],
            [Banner::class, 'image_url'],
            [Partner::class, 'image'],
            [Client::class, 'logo_url'],
            [BlogPost::class, 'image_path'],
            [User::class, 'avatar_path'],
            [CartItem::class, 'image'],
            [Wishlist::class, 'image'],
        ];

        foreach ($maps as [$model, $column]) {
            $model::query()->whereNotNull($column)->where($column, '!=', '')
                ->get()
                ->each(function ($row) use ($column): void {
                    $normalized = $this->normalizePath($row->{$column});
                    if ($normalized !== $row->{$column}) {
                        $row->update([$column => $normalized]);
                    }
                });
            $this->line("  normalize {$model}::{$column}");
        }
    }

    private function normalizeSettings(): void
    {
        $appearance = Setting::where('group', 'appearance')->where('key', 'featuredServices')->first();
        if ($appearance) {
            $value = $appearance->value;
            foreach ($value as &$service) {
                if (isset($service['image'])) {
                    $service['image'] = $this->normalizePath($service['image']);
                }
            }
            unset($service);
            $appearance->update(['value' => $value]);
            $this->line('  normalize appearance.featuredServices');
        }

        Setting::where('group', 'product-pages')->get()->each(function (Setting $setting): void {
            $page = $setting->value;
            if (isset($page['gallery']['items']) && is_array($page['gallery']['items'])) {
                foreach ($page['gallery']['items'] as &$item) {
                    $item['image'] = $this->normalizePath($item['image']);
                }
                unset($item);
            }
            $setting->update(['value' => $page]);
        });
        $this->line('  normalize settings.product-pages');
    }

    private function rewriteProductPagesJson(): void
    {
        $pages = Setting::where('group', 'product-pages')
            ->pluck('value', 'key')
            ->all();

        if ($pages === []) {
            return;
        }

        $path = database_path('data/product-pages.json');
        File::put($path, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('  rewrote '.$path);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        // Gambar yang tidak memiliki sumber lokal: fallback picsum.photos
        // (deterministik via seed = nama file, ukuran sesuai konteks).
        $missing = [
            'promo-akhir.jpg' => '800,400',  // Banner promo horizontal.
            'banner.jpg' => '800,600',       // Produk Banner Vinyl/Flexi.
        ];
        $name = basename($path);
        if (isset($missing[$name])) {
            [$w, $h] = explode(',', $missing[$name]);

            return "https://picsum.photos/seed/{$name}/{$w}/{$h}";
        }

        // Gallery desain product pages: URL eksternal skala.or.id → storage lokal.
        if (str_starts_with($path, 'https://skala.or.id/')) {
            $name = basename(parse_url($path, PHP_URL_PATH) ?: '');
            return "/storage/images/gallery/{$name}";
        }

        if (str_starts_with($path, '/images/')) {
            return '/storage/'.substr($path, 1);
        }

        return $path;
    }
}