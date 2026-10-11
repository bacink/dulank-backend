<?php

namespace App\Models;

use App\Enums\CustomerChannel;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'customer_type_id',
        'channel',
        'date_join',
        'last_seen',
    ];

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CustomerChannel::class,
            'date_join' => 'date',
            'last_seen' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CustomerType, $this>
     */
    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }
}
