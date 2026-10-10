<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'code' => 'CAT-'.fake()->unique()->numerify('####'),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'status' => 'Active',
        ];
    }
}
