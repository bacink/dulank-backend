<?php

namespace App\Models;

use Database\Factories\BanIpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BanIp extends Model
{
    /** @use HasFactory<BanIpFactory> */
    use HasFactory;

    protected $fillable = ['ip_address', 'reason', 'status'];
}
