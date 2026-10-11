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
        'subscriber', 'plan', 'plan_tier', 'billing_cycle', 'payment_method',
        'amount', 'created_date', 'expiring_on', 'status',
    ];

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
