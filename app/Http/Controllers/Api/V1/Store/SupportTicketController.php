<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\TicketAuthorType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreTicketReplyRequest;
use App\Http\Requests\Store\StoreTicketRequest;
use App\Http\Resources\Store\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(SupportTicketResource::collection($tickets));
    }

    public function show(Request $request, string $ticketNo): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->where('ticket_no', $ticketNo)
            ->with('messages')
            ->first();

        if ($ticket === null) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        $this->authorize('view', $ticket);

        return ApiResponse::success(new SupportTicketResource($ticket));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = SupportTicket::query()->create([
            'ticket_no' => DocumentNumber::next('ticket', SupportTicket::query(), 'ticket_no', 3),
            'user_id' => $request->user()->id,
            'subject' => $request->string('subject')->toString(),
            'type' => $request->input('type') ?? 'Info Inquiry',
            'priority' => TicketPriority::fromLegacy((string) ($request->input('priority') ?? 'medium')),
            'status' => TicketStatus::Open,
            'created_date' => now()->toDateString(),
            'description' => $request->string('message')->toString(),
        ]);

        return ApiResponse::success(new SupportTicketResource($ticket->load('messages')));
    }

    public function reply(StoreTicketReplyRequest $request, string $ticketNo): JsonResponse
    {
        $ticket = SupportTicket::query()->where('ticket_no', $ticketNo)->first();

        if ($ticket === null) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        $this->authorize('reply', $ticket);

        $ticket->messages()->create([
            'author_type' => TicketAuthorType::User,
            'author_id' => $request->user()->id,
            'message' => $request->string('message')->toString(),
        ]);

        if ($request->boolean('close')) {
            $ticket->forceFill(['status' => TicketStatus::Closed])->save();

            SupportTicketActivity::query()->create([
                'support_ticket_id' => $ticket->id,
                'type' => 'status',
                'title' => 'Ticket closed',
                'description' => 'Tiket ditutup oleh pelanggan.',
                'author' => $request->user()->name,
                'status_color' => 'danger',
                'occurred_at' => now(),
            ]);
        } elseif ($ticket->status === TicketStatus::Closed || $ticket->status === TicketStatus::Resolved) {
            $ticket->forceFill(['status' => TicketStatus::Open])->save();
        }

        return ApiResponse::success(new SupportTicketResource($ticket->load('messages')));
    }
}
