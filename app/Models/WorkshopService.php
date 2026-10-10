<?php

namespace App\Models;

use Database\Factories\WorkshopServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopService extends Model
{
    /** @use HasFactory<WorkshopServiceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'category', 'specification', 'price', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specification' => 'array',
            'price' => 'integer',
        ];
    }
}
