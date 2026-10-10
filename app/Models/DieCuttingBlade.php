<?php

namespace App\Models;

use Database\Factories\DieCuttingBladeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DieCuttingBlade extends Model
{
    /** @use HasFactory<DieCuttingBladeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'price_per_cm', 'published', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_cm' => 'integer',
            'published' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
