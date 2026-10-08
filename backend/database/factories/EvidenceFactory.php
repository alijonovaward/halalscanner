<?php

namespace Database\Factories;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Models\Evidence;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'ingredient_id' => null,
            'certificate_id' => null,
            'source_type' => SourceType::ManualReview,
            'trust_tier' => TrustTier::Unreviewed,
            'evidence_strength' => EvidenceStrength::Inferred,
            'direction' => EvidenceDirection::SupportsHalal,
            'review_status' => ReviewStatus::Pending,
            'checked_at' => now(),
            'expires_at' => null,
            'source_reference' => fake()->url(),
            'notes' => null,
        ];
    }
}
