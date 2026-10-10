<?php

namespace App\Models;

use Database\Factories\OutputTaxDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutputTaxDocument extends Model
{
    /** @use HasFactory<OutputTaxDocumentFactory> */
    use HasFactory;

    protected $fillable = ['order_id', 'etax_date', 'etax_number', 'tx_code', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etax_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
