<?php

namespace Tests\Feature\Database;

use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Models\Evidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EvidenceSourceMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_trusted_evidence_requires_checked_date_and_source_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Approved trusted evidence requires checked_at and source_reference.');

        Evidence::factory()->create([
            'source_type' => SourceType::Certificate,
            'trust_tier' => TrustTier::Trusted,
            'evidence_strength' => EvidenceStrength::CertifiedSource,
            'review_status' => ReviewStatus::Approved,
            'checked_at' => null,
            'source_reference' => null,
        ]);
    }

    public function test_user_report_evidence_cannot_be_marked_authoritative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User report evidence cannot be marked trusted or approved.');

        Evidence::factory()->create([
            'source_type' => SourceType::UserReport,
            'trust_tier' => TrustTier::Trusted,
            'evidence_strength' => EvidenceStrength::UserReport,
            'review_status' => ReviewStatus::Approved,
        ]);
    }
}
