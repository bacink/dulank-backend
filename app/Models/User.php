<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'type',
        'phone',
        'password',
        'avatar_path',
        'join_date',
        'last_login_at',
        'company_type',
        'company_name',
        'department_name',
        'npwp',
        'transaction_code',
        'tax_address',
        'attach_tax_invoice',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'join_date' => 'date',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'attach_tax_invoice' => 'boolean',
            'type' => AccountType::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasEnabledTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->type === AccountType::Admin;
    }

    public function isStore(): bool
    {
        return $this->type === AccountType::Store;
    }

    /**
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }
}
