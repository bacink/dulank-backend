<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Penyimpanan media ke disk `public` + resolusi URL.
 */
final class MediaService
{
    public function disk(): string
    {
        return (string) config('dulank.uploads.disk', 'public');
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, $this->disk());

        if (! is_string($path) || $path === '') {
            throw new ApiException('Gagal menyimpan file.', 500);
        }

        return $path;
    }

    /**
     * URL final untuk path tersimpan; path legacy (`/images/...`) dikembalikan apa adanya.
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk($this->disk())->url($path);
    }
}
