<?php

namespace Database\Factories;

use App\Enums\MachineKind;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'printing_shop',
            'name' => fake()->company(),
            'province' => 'Jawa Barat',
            'city' => 'Karawang',
            'district' => 'Karawang Barat',
            'followers_label' => '1,2RB',
            'following_count' => 86,
            'image' => '/storage/images/tab.png',
            'status' => 'Active',
        ];
    }

    public function paperStore(): static
    {
        return $this->state(['type' => 'paper_store']);
    }
}