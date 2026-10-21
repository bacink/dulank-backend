<?php

namespace App\Models;

use Database\Factories\UserAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdmin extends Model
{
    /** @use HasFactory<UserAdminFactory> */
    use HasFactory;

    protected $fillable = ['all_stores', 'user_id', 'admin_code', 'role', 'stores', 'status', 'descriptions'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stores' => 'array',
            'all_stores' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
