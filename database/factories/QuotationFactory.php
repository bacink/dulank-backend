<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(100, 900) * 1000;

        return [
            'no_quotation' => 'QT-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'status' => 'Draft',
            'channel' => 'online',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'sub_total' => $total,
            'tax' => 0,
            'shipping_cost' => 0,
            'total' => $total,
        ];
    }
}
