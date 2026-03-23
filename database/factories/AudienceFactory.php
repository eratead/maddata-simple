<?php

namespace Database\Factories;

use App\Models\Audience;
use Illuminate\Database\Eloquent\Factories\Factory;

class AudienceFactory extends Factory
{
    protected $model = Audience::class;

    public function definition(): array
    {
        return [
            'provider' => $this->faker->randomElement(['Google', 'Meta', 'Amazon', null]),
            'icon' => null,
            'main_category' => $this->faker->randomElement(['Demographics', 'In-Market', 'Interests', 'Lifestyle']),
            'sub_category' => $this->faker->randomElement(['Family', 'Education', 'Home', 'Tech', 'Travel']),
            'name' => $this->faker->unique()->words(3, true),
            'full_path' => null,
            'estimated_users' => $this->faker->optional()->numberBetween(10000, 5000000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
