<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    /** @use HasFactory<\Database\Factories\SalesReturnItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'name',
        'description',
        'qty_order',
        'qty_return',
        'unit',
        'price',
        'return_amount',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_order' => 'float',
            'qty_return' => 'float',
            'price' => 'integer',
            'return_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }
}
