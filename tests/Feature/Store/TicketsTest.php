<?php

use App\Models\SupportTicket;
use App\Models\User;

it('creates a support ticket with legacy shape and document number', function () {
    $user = User::factory()->create();

    $response = $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Pesanan belum diterima',
            'message' => 'Sudah 5 hari belum sampai.',
            'type' => 'Complaint',
            'priority' => 'high',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.id', 'TK-001')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.replies', [])
        ->assertJsonStructure(['data' => ['id', 'userId', 'subject', 'type', 'message', 'priority', 'status', 'date', 'replies']]);

    $this->assertDatabaseHas('support_tickets', ['ticket_no' => 'TK-001', 'user_id' => $user->id]);
});

it('adds a reply and closes the ticket', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->for($user)->create(['ticket_no' => 'TK-010']);
    $token = $user->createToken('t', ['store'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/tickets/TK-010/replies', ['message' => 'Tambahan info'])
        ->assertOk()
        ->assertJsonCount(1, 'data.replies')
        ->assertJsonPath('data.replies.0.from', 'user')
        ->assertJsonPath('data.replies.0.message', 'Tambahan info');

    $this->withToken($token)->postJson('/api/v1/tickets/TK-010/replies', ['message' => 'Selesai', 'close' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $this->assertDatabaseHas('support_ticket_activities', ['support_ticket_id' => $ticket->id, 'type' => 'status']);
});

it('reopens a resolved ticket when the user replies', function () {
    $user = User::factory()->create();
    SupportTicket::factory()->for($user)->create(['ticket_no' => 'TK-011', 'status' => 'Resolved']);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->postJson('/api/v1/tickets/TK-011/replies', ['message' => 'Masih bermasalah'])
        ->assertOk()
        ->assertJsonPath('data.status', 'open');
});

it('prevents access to other users tickets', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    SupportTicket::factory()->for($other)->create(['ticket_no' => 'TK-012']);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->getJson('/api/v1/tickets/TK-012')
        ->assertStatus(403);

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->postJson('/api/v1/tickets/TK-012/replies', ['message' => 'Halo'])
        ->assertStatus(403);
});

it('validates required ticket fields', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('t', ['store'])->plainTextToken)
        ->postJson('/api/v1/tickets', ['subject' => '', 'message' => ''])
        ->assertStatus(422)
        ->assertJsonStructure(['success', 'message', 'errors' => ['subject', 'message']]);
});
