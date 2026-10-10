<?php

namespace App\Models;

use Database\Factories\ProductProcessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductProcess extends Model
{
    /** @use HasFactory<ProductProcessFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'code', 'process_name', 'image_path', 'status'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
