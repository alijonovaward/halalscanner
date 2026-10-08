<?php

namespace Tests\Unit\Domain\Halal;

use App\Domain\Halal\FiqhClassification;
use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\VerdictResult;
use App\Domain\Halal\VerdictStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VerdictResultTest extends TestCase
{
    public function test_public_statuses_are_limited_to_mvp_set(): void
    {
        $this->assertSame(
            ['halal', 'haram', 'mushbooh', 'unknown'],
            array_map(
                static fn (VerdictStatus $status): string => $status->value,
                VerdictStatus::cases(),
            ),
        );
    }

    public function test_makruh_is_internal_fiqh_classification_not_public_status(): void
    {
        $publicStatuses = array_map(
            static fn (VerdictStatus $status): string => $status->value,
            VerdictStatus::cases(),
        );

        $internalClassifications = array_map(
            static fn (FiqhClassification $classification): string => $classification->value,
            FiqhClassification::cases(),
        );

        $this->assertNotContains('makruh_tanzihi', $publicStatuses);
        $this->assertNotContains('makruh_tahrimi', $publicStatuses);
        $this->assertContains('makruh_tanzihi', $internalClassifications);
        $this->assertContains('makruh_tahrimi', $internalClassifications);
    }

    public function test_halal_verdict_requires_sufficient_positive_evidence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A halal verdict requires sufficient positive evidence.');

        VerdictResult::make(
            status: VerdictStatus::Halal,
            hasSufficientPositiveEvidence: false,
            hasBlockingRisk: false,
            reasonCodes: [ReasonCode::InsufficientEvidence],
        );
    }

    public function test_halal_verdict_cannot_ignore_blocking_risk(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A halal verdict cannot be issued while a blocking risk is unresolved.');

        VerdictResult::make(
            status: VerdictStatus::Halal,
            hasSufficientPositiveEvidence: true,
            hasBlockingRisk: true,
            reasonCodes: [ReasonCode::SourceConflict],
        );
    }

    public function test_mushbooh_verdict_can_hold_insufficient_evidence_reason(): void
    {
        $result = VerdictResult::make(
            status: VerdictStatus::Mushbooh,
            hasSufficientPositiveEvidence: false,
            hasBlockingRisk: false,
            reasonCodes: [ReasonCode::InsufficientEvidence],
        );

        $this->assertSame(VerdictStatus::Mushbooh, $result->status);
        $this->assertSame([ReasonCode::InsufficientEvidence], $result->reasonCodes);
    }
}
