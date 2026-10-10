<?php

namespace App\Models;

use Database\Factories\InputTaxDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InputTaxDocument extends Model
{
    /** @use HasFactory<InputTaxDocumentFactory> */
    use HasFactory;

    protected $fillable = ['purchase_id', 'invoice_date', 'faktur_no', 'credited'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
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
