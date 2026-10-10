<?php

namespace Database\Factories;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(100, 900) * 1000;

        return [
            'number' => 'SO-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory(),
            'channel' => OrderChannel::Website,
            'status' => OrderStatus::Pending,
            'shipping_address' => [
                'name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'type' => 'Office',
                'street' => fake()->streetAddress(),
                'district' => 'TELUK JAMBE TIMUR',
                'city' => 'KAB. KARAWANG',
                'province' => 'JAWA BARAT',
                'postalCode' => '41360',
                'country' => 'ID',
            ],
            'sub_total' => $total,
            'total' => $total,
            'paid' => 0,
            'due' => $total,
            'payment_status' => PaymentStatus::Unpaid,
            'ordered_at' => now(),
        ];
    }
}
