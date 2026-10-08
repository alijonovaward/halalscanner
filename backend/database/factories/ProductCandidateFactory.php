<?php

namespace Database\Factories;

use App\Models\ProductCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCandidate>
 */
class ProductCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'ingredients_text' => fake()->sentence(),
            'source_url' => fake()->url(),
            'source_modified_at' => now(),
            'source_dataset' => 'Open Food Facts',
            'source_license' => 'ODbL-1.0',
            'market_tags' => ['en:uzbekistan'],
            'review_status' => 'pending_review',
            'data_quality_flags' => [],
        ];
    }
}
