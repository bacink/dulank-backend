<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_no' => 'TK-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'subject' => fake()->sentence(),
            'type' => 'Info Inquiry',
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Open,
            'created_date' => now()->toDateString(),
            'description' => fake()->paragraph(),
        ];
    }
}
