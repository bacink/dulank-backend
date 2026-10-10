<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));
        $priceFrom = fake()->numberBetween(10, 100) * 1000;

        return [
            'code' => 'PRD-'.fake()->unique()->numerify('#####'),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => $name,
            'category_id' => Category::factory(),
            'description' => fake()->sentence(),
            'price' => $priceFrom,
            'price_from' => $priceFrom,
            'price_to' => $priceFrom * 5,
            'price_type' => 'Range',
            'quantity' => 0,
            'status' => ProductStatus::Active,
        ];
    }
}
