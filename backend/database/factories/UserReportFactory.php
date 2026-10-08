<?php

namespace Database\Factories;

use App\Domain\Halal\ReviewStatus;
use App\Models\Product;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserReport>
 */
class UserReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'ingredient_id' => null,
            'report_type' => 'verdict_dispute',
            'review_status' => ReviewStatus::Pending,
            'reason_code' => null,
            'submitted_evidence' => fake()->sentence(),
            'source_reference' => null,
            'reporter_contact_hash' => null,
            'reviewer_decision' => null,
            'reviewed_at' => null,
        ];
    }
}
