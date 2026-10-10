<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RequestQuotation extends Model
{
    /** @use HasFactory<\Database\Factories\RequestQuotationFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_request',
        'customer',
        'email',
        'telp',
        'date',
        'status',
        'due_date',
        'payment_term',
        'document',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'document' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
