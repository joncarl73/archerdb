<?php

// database/factories/CompanyFactory.php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'company_name' => $this->faker->company(),
            'pricing_tier_id' => null,
        ];
    }
}
