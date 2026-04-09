<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'action' => $this->faker->randomElement(['created', 'updated', 'deleted']),
            'description' => $this->faker->sentence(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
