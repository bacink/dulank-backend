<?php

namespace App\Models;

use Database\Factories\DownloadFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadFile extends Model
{
    /** @use HasFactory<DownloadFileFactory> */
    use HasFactory;

    protected $fillable = ['name', 'type', 'size', 'path', 'date'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
