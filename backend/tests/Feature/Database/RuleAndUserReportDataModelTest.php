<?php

namespace Tests\Feature\Database;

use App\Domain\Halal\FiqhClassification;
use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\VerdictStatus;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Rule;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleAndUserReportDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingredient_rule_stores_madhhab_fiqh_and_review_metadata(): void
    {
        $ingredient = Ingredient::factory()->create([
            'canonical_name' => 'Gelatin',
            'origin' => 'unknown',
        ]);

        $rule = Rule::factory()
            ->for($ingredient)
            ->create([
                'scope' => 'ingredient',
                'madhhab' => 'hanafi',
                'public_verdict_effect' => VerdictStatus::Mushbooh,
                'fiqh_classification' => FiqhClassification::MakruhTahrimi,
                'reason_code' => ReasonCode::MakruhTahrimi,
                'requires_origin_evidence' => true,
                'requires_scholar_review' => true,
                'review_status' => ReviewStatus::Pending,
            ]);

        $this->assertTrue($ingredient->rules()->whereKey($rule)->exists());
        $this->assertSame('hanafi', $rule->madhhab);
        $this->assertSame(FiqhClassification::MakruhTahrimi, $rule->fiqh_classification);
        $this->assertTrue($rule->requires_scholar_review);
    }

    public function test_user_report_stays_pending_and_does_not_create_authoritative_evidence(): void
    {
        $product = Product::factory()->create([
            'barcode' => '4780000000029',
        ]);

        $report = UserReport::factory()
            ->for($product)
            ->create([
                'report_type' => 'verdict_dispute',
                'review_status' => ReviewStatus::Pending,
                'submitted_evidence' => 'The package says halal.',
            ]);

        $this->assertTrue($product->userReports()->whereKey($report)->exists());
        $this->assertSame(ReviewStatus::Pending, $report->review_status);
        $this->assertSame(0, $product->evidence()->where('source_type', SourceType::UserReport->value)->count());
    }
}
