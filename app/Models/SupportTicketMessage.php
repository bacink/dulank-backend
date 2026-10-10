<?php

namespace App\Models;

use App\Enums\TicketAuthorType;
use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    protected $fillable = ['support_ticket_id', 'author_type', 'author_id', 'message'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author_type' => TicketAuthorType::class,
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
