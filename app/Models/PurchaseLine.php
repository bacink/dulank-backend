<?php

namespace App\Models;

use Database\Factories\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    /** @use HasFactory<PurchaseLineFactory> */
    use HasFactory;

    protected $fillable = ['purchase_id', 'name', 'qty', 'unit', 'price', 'total'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'price' => 'integer',
            'total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
