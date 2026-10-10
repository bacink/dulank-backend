<?php

namespace App\Http\Resources\Store;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ticket_no,
            'userId' => (int) $this->user_id,
            'subject' => $this->subject,
            'type' => $this->type,
            'message' => (string) $this->description,
            'priority' => $this->priority?->legacy(),
            'status' => $this->status?->legacy(),
            'date' => $this->created_date?->toDateString(),
            'replies' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message) => [
                'from' => $message->author_type->value,
                'message' => $message->message,
                'date' => $message->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
