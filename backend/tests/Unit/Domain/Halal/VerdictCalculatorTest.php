<?php

namespace Tests\Unit\Domain\Halal;

use App\Domain\Halal\Evidence;
use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\VerdictCalculator;
use App\Domain\Halal\VerdictStatus;
use PHPUnit\Framework\TestCase;

class VerdictCalculatorTest extends TestCase
{
    public function test_no_evidence_returns_unknown_not_halal(): void
    {
        $result = VerdictCalculator::fromEvidence([]);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_unverified_package_halal_claim_returns_mushbooh_not_halal(): void
    {
        $result = VerdictCalculator::fromEvidence([
            Evidence::unverifiedPackageHalalClaim(),
        ]);

        $this->assertSame(VerdictStatus::Mushbooh, $result->status);
        $this->assertContains(ReasonCode::CertificateMissing, $result->reasonCodes);
    }

    public function test_user_report_does_not_create_halal_verdict(): void
    {
        $result = VerdictCalculator::fromEvidence([
            Evidence::userHalalReport(),
        ]);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_trusted_halal_certificate_can_support_halal_when_no_conflict_exists(): void
    {
        $result = VerdictCalculator::fromEvidence([
            Evidence::trustedHalalCertificate(),
        ]);

        $this->assertSame(VerdictStatus::Halal, $result->status);
        $this->assertTrue($result->hasSufficientPositiveEvidence);
        $this->assertFalse($result->hasBlockingRisk);
    }

    public function test_conflicting_trusted_sources_return_mushbooh(): void
    {
        $result = VerdictCalculator::fromEvidence([
            Evidence::trustedHalalCertificate(),
            Evidence::trustedHaramRule(),
        ]);

        $this->assertSame(VerdictStatus::Mushbooh, $result->status);
        $this->assertContains(ReasonCode::SourceConflict, $result->reasonCodes);
    }
}
