<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignData;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignDataFactory extends Factory
{
    protected $model = CampaignData::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'report_date' => $this->faker->date(),
            'impressions' => $this->faker->numberBetween(100, 1000),
            'clicks' => $this->faker->numberBetween(10, 100),
            'visible_impressions' => $this->faker->numberBetween(50, 800),
            'uniques' => $this->faker->numberBetween(50, 900),
        ];
    }
}
