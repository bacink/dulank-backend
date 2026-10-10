<?php

namespace App\Models;

use Database\Factories\WorkshopServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopService extends Model
{
    /** @use HasFactory<WorkshopServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'category',
        'printing_type',
        'name',
        'max_height',
        'max_width',
        'size_unit',
        'quantity_minimum',
        'price',
        'price_unit',
        'druck_price',
        'min_size',
        'max_size',
        'price_per_cm',
        'minimum_price',
        'standard_price',
        'standard_minimum',
        'half_cut_price',
        'half_cut_minimum',
        'minimum_calculation',
        'specification',
        'status',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specification' => 'array',
            'price' => 'integer',
            'max_height' => 'float',
            'max_width' => 'float',
            'quantity_minimum' => 'float',
            'druck_price' => 'integer',
            'price_per_cm' => 'integer',
            'minimum_price' => 'integer',
            'standard_price' => 'integer',
            'standard_minimum' => 'integer',
            'half_cut_price' => 'integer',
            'half_cut_minimum' => 'integer',
            'minimum_calculation' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
