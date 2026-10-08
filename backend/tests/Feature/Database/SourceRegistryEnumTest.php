<?php

namespace Tests\Feature\Database;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\FiqhClassification;
use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Domain\Halal\VerdictStatus;
use App\Models\Evidence;
use App\Models\Rule;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceRegistryEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_source_registry_fields_are_cast_to_enums(): void
    {
        $evidence = Evidence::factory()->create([
            'source_type' => SourceType::Certificate,
            'trust_tier' => TrustTier::Trusted,
            'evidence_strength' => EvidenceStrength::CertifiedSource,
            'direction' => EvidenceDirection::SupportsHalal,
            'review_status' => ReviewStatus::Approved,
        ])->refresh();

        $this->assertSame(SourceType::Certificate, $evidence->source_type);
        $this->assertSame(TrustTier::Trusted, $evidence->trust_tier);
        $this->assertSame(EvidenceStrength::CertifiedSource, $evidence->evidence_strength);
        $this->assertSame(EvidenceDirection::SupportsHalal, $evidence->direction);
        $this->assertSame(ReviewStatus::Approved, $evidence->review_status);
        $this->assertSame('certificate', $evidence->getRawOriginal('source_type'));
    }

    public function test_rule_verdict_metadata_is_cast_to_domain_enums(): void
    {
        $rule = Rule::factory()->create([
            'public_verdict_effect' => VerdictStatus::Mushbooh,
            'fiqh_classification' => FiqhClassification::MakruhTahrimi,
            'reason_code' => ReasonCode::MakruhTahrimi,
            'review_status' => ReviewStatus::Pending,
        ])->refresh();

        $this->assertSame(VerdictStatus::Mushbooh, $rule->public_verdict_effect);
        $this->assertSame(FiqhClassification::MakruhTahrimi, $rule->fiqh_classification);
        $this->assertSame(ReasonCode::MakruhTahrimi, $rule->reason_code);
        $this->assertSame(ReviewStatus::Pending, $rule->review_status);
    }

    public function test_user_report_review_status_is_cast_to_enum(): void
    {
        $report = UserReport::factory()->create([
            'review_status' => ReviewStatus::Pending,
        ])->refresh();

        $this->assertSame(ReviewStatus::Pending, $report->review_status);
        $this->assertSame('pending', $report->getRawOriginal('review_status'));
    }
}
