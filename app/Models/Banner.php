<?php

namespace App\Models;

use App\Enums\BannerType;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'image_url',
        'type',
        'position',
        'redirect_url',
        'start_date',
        'end_date',
        'status',
        'sort_order',
        'description',
        'discount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BannerType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @param  Builder<Banner>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'Active')
            ->where(function (Builder $query) {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }
}
