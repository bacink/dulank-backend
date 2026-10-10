<?php

namespace Database\Factories;

use App\Enums\CartItemStatus;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'image' => null,
            'quantity' => 1,
            'price' => fake()->numberBetween(10, 90) * 1000,
            'spec' => '',
            'status' => CartItemStatus::Active,
        ];
    }
}
