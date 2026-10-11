<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Permintaan penghapusan akun dari pengguna.
 */
class DeleteAccountRequest extends Model
{
    use HasFactory;

    protected $table = 'delete_account_requests';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'email', 'user_name', 'avatar', 'requisition_date', 'delete_request_date', 'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requisition_date' => 'date',
            'delete_request_date' => 'date',
        ];
    }
}
