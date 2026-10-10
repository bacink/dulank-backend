<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 900) * 1000;

        return [
            'invoice_no' => 'INV-'.fake()->unique()->numerify('######'),
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => $amount,
            'paid' => 0,
            'amount_due' => $amount,
            'status' => InvoiceStatus::Unpaid,
        ];
    }
}
