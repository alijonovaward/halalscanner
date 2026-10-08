<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificationBody;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'certification_body_id' => CertificationBody::factory(),
            'certificate_number' => strtoupper(fake()->bothify('HAL-####-????')),
            'scope' => 'product',
            'status' => 'unknown',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'verified_at' => null,
            'source_url' => fake()->url(),
            'notes' => null,
        ];
    }
}
