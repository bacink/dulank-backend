<?php

use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\DocumentNumber;

function makeTicket(User $user, string $number, string $subject = 'Tiket'): SupportTicket
{
    return SupportTicket::query()->create([
        'ticket_no' => $number,
        'user_id' => $user->id,
        'subject' => $subject,
        'priority' => 'Medium',
        'status' => 'Open',
        'description' => 'pesan',
    ]);
}

it('generates sequential document numbers from the config prefix', function () {
    $user = User::factory()->create();

    makeTicket($user, 'TK-001');
    makeTicket($user, 'TK-002');

    expect(DocumentNumber::next('ticket', SupportTicket::query(), 'ticket_no', 3))->toBe('TK-003');
});

it('prefers the prefixes setting over config', function () {
    Setting::query()->create([
        'group' => 'prefixes',
        'key' => 'ticket',
        'value' => ['prefix' => 'TC'],
    ]);

    expect(DocumentNumber::prefix('ticket'))->toBe('TC');
});

it('skips a candidate number that already exists', function () {
    $user = User::factory()->create();

    makeTicket($user, 'TK-002');

    expect(DocumentNumber::next('ticket', SupportTicket::query(), 'ticket_no', 3))->toBe('TK-003');
});

it('falls back to the type name when no config prefix exists', function () {
    expect(DocumentNumber::prefix('unknown_type'))->toBe('UNKNOWN_TYPE');
});
