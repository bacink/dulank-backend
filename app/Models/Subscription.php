<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Subskripsi pelanggan (paket toko di webstore).
 */
class Subscription extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id', 'subscriber', 'plan', 'plan_tier', 'billing_cycle', 'payment_method',
        'amount', 'created_date', 'expiring_on', 'status',
    ];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_date' => 'date',
            'expiring_on' => 'date',
        ];
    }
}
