<?php

namespace App\Models;

use Database\Factories\CustomerTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerType extends Model
{
    /** @use HasFactory<CustomerTypeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'status'];

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
