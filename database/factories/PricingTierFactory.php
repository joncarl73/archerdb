<?php

// database/factories/PricingTierFactory.php

namespace Database\Factories;

use App\Models\PricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingTierFactory extends Factory
{
    protected $model = PricingTier::class;

    public function definition(): array
    {
        return [
            'name' => 'Standard '.$this->faker->unique()->word(),
            'league_participant_fee_cents' => 200,   // $2 default
            'competition_participant_fee_cents' => 200,
            'currency' => 'usd',
            'is_active' => true,
        ];
    }
}
