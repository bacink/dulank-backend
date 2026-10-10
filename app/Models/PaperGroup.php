<?php

namespace App\Models;

use App\Enums\PaperOwnerType;
use Database\Factories\PaperGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaperGroup extends Model
{
    /** @use HasFactory<PaperGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'partner_id',
        'source',
        'name',
        'merk',
        'price_type',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => PaperOwnerType::class,
        ];
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return HasMany<PaperType, $this>
     */
    public function types(): HasMany
    {
        return $this->hasMany(PaperType::class);
    }

    /**
     * @return HasMany<PaperPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PaperPrice::class);
    }

    /**
     * @return HasMany<PaperSize, $this>
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(PaperSize::class);
    }
}
