<?php

namespace Database\Factories;

use App\Enums\WishlistStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
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
            'price' => fake()->numberBetween(10, 90) * 1000,
            'spec' => '',
            'status' => WishlistStatus::Active,
            'added_at' => now()->toDateString(),
            'expire_date' => null,
        ];
    }
}
