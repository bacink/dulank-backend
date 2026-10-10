<?php

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'avatar',
        'province',
        'city',
        'district',
        'address',
        'join_date',
        'subscribed',
        'whatsapp',
        'followers_label',
        'followers_count',
        'following_count',
        'image',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'subscribed' => 'boolean',
        ];
    }

    /**
     * @return HasOne<PartnerMetric, $this>
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(PartnerMetric::class);
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
