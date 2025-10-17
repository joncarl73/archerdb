<?php

// database/factories/LeagueFactory.php

namespace Database\Factories;

use App\Models\Company;
use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LeagueFactory extends Factory
{
    protected $model = League::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'company_id' => Company::factory(),
            'title' => 'League '.Str::random(5),
            'location' => $this->faker->city(),
            'length_weeks' => 8,
            'day_of_week' => 3,
            'start_date' => now()->toDateString(),
            'type' => 'open',           // or 'closed' as needed
            'is_published' => false,
            // put any NOT NULL columns your migration enforces here with sensible defaults
            'lanes_count' => 10,
            'lane_breakdown' => 'single',
            'ends_per_day' => 10,
            'arrows_per_end' => 3,
            'x_ring_value' => 10,
            'scoring_mode' => 'personal_device',
        ];
    }
}
