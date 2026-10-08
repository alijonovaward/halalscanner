<?php

namespace Database\Factories;

use App\Models\CertificationBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationBody>
 */
class CertificationBodyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'country_code' => fake()->countryCode(),
            'trust_status' => 'unknown',
            'website' => fake()->url(),
            'notes' => null,
        ];
    }
}
