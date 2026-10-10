<?php

namespace App\Models;

use App\Enums\QuotationChannel;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_quotation',
        'user_id',
        'customer_id',
        'date',
        'due_date',
        'valid_until',
        'status',
        'channel',
        'customer_name',
        'customer_email',
        'customer_phone',
        'sub_total',
        'discount',
        'tax_rate',
        'tax',
        'shipping_cost',
        'total',
        'document',
        'order_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'valid_until' => 'date',
            'channel' => QuotationChannel::class,
            'sub_total' => 'integer',
            'discount' => 'integer',
            'tax_rate' => 'float',
            'tax' => 'integer',
            'shipping_cost' => 'integer',
            'total' => 'integer',
            'document' => 'array',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
