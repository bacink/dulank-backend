<?php

namespace App\Models;

use Database\Factories\BankAccountTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccountType extends Model
{
    /** @use HasFactory<BankAccountTypeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'status'];

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
