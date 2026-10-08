<?php

namespace Database\Factories;

use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\VerdictStatus;
use App\Models\Rule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => null,
            'ingredient_id' => null,
            'scope' => 'ingredient',
            'madhhab' => 'hanafi',
            'public_verdict_effect' => VerdictStatus::Mushbooh,
            'fiqh_classification' => null,
            'reason_code' => ReasonCode::UnknownOrigin,
            'requires_origin_evidence' => true,
            'requires_process_evidence' => false,
            'requires_scholar_review' => false,
            'requires_editor_review' => true,
            'review_status' => ReviewStatus::Pending,
            'source_reference' => fake()->url(),
            'explanation_template' => 'This ingredient requires verified evidence before a permissive verdict.',
        ];
    }
}
