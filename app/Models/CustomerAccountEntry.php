<?php

namespace App\Models;

use Database\Factories\CustomerAccountEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccountEntry extends Model
{
    /** @use HasFactory<CustomerAccountEntryFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'source_type', 'source_id', 'amount', 'occurred_at', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
