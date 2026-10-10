<?php

namespace App\Models;

use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_category_id',
        'code',
        'name',
        'description',
        'merk',
        'price',
        'unit',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PurchaseCategory::class, 'purchase_category_id');
    }
}
