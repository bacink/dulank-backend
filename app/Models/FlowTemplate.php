<?php

namespace App\Models;

use Database\Factories\FlowTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowTemplate extends Model
{
    /** @use HasFactory<FlowTemplateFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'information'];
}
