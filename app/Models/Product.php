<?php

namespace App\Models;

use App\Enums\PriceType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'slug',
        'name',
        'category_id',
        'sub_category_id',
        'unit_id',
        'store_id',
        'description',
        'image_path',
        'price',
        'price_from',
        'price_to',
        'price_type',
        'product_type',
        'selling_type',
        'quantity',
        'min_order_qty',
        'quantity_alert',
        'min_price',
        'druck_price',
        'min_length',
        'min_width',
        'discount_type',
        'discount_value',
        'tax_type',
        'status',
        'archived_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'price_from' => 'integer',
            'price_to' => 'integer',
            'quantity' => 'float',
            'min_order_qty' => 'float',
            'quantity_alert' => 'float',
            'min_price' => 'integer',
            'druck_price' => 'integer',
            'min_length' => 'float',
            'min_width' => 'float',
            'discount_value' => 'integer',
            'price_type' => PriceType::class,
            'product_type' => ProductType::class,
            'status' => ProductStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)->whereNull('archived_at');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<SubCategory, $this>
     */
    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductSpec, $this>
     */
    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductProcess, $this>
     */
    public function processes(): HasMany
    {
        return $this->hasMany(ProductProcess::class);
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
