<?php

namespace App\Domain\Halal;

use InvalidArgumentException;

final class VerdictCalculator
{
    /**
     * @param  list<Evidence>  $evidenceItems
     */
    public static function fromEvidence(array $evidenceItems): VerdictResult
    {
        self::guardEvidenceItems($evidenceItems);

        if ($evidenceItems === []) {
            return self::unknown([ReasonCode::InsufficientEvidence]);
        }

        $trustedPositiveEvidence = self::filter(
            $evidenceItems,
            static fn (Evidence $evidence): bool => $evidence->supportsHalal()
                && $evidence->isTrusted
                && $evidence->isSufficientForHalal,
        );

        $trustedBlockingEvidence = self::filter(
            $evidenceItems,
            static fn (Evidence $evidence): bool => $evidence->blocksHalal() && $evidence->isTrusted,
        );

        if ($trustedPositiveEvidence !== [] && $trustedBlockingEvidence !== []) {
            return VerdictResult::make(
                status: VerdictStatus::Mushbooh,
                hasSufficientPositiveEvidence: true,
                hasBlockingRisk: true,
                reasonCodes: [ReasonCode::SourceConflict],
            );
        }

        if ($trustedBlockingEvidence !== []) {
            return VerdictResult::make(
                status: VerdictStatus::Haram,
                hasSufficientPositiveEvidence: false,
                hasBlockingRisk: true,
                reasonCodes: [ReasonCode::BlockingRisk],
            );
        }

        if ($trustedPositiveEvidence !== []) {
            return VerdictResult::make(
                status: VerdictStatus::Halal,
                hasSufficientPositiveEvidence: true,
                hasBlockingRisk: false,
                reasonCodes: [],
            );
        }

        if (self::containsKind($evidenceItems, EvidenceKind::PackageHalalClaim)) {
            return VerdictResult::make(
                status: VerdictStatus::Mushbooh,
                hasSufficientPositiveEvidence: false,
                hasBlockingRisk: false,
                reasonCodes: [ReasonCode::CertificateMissing],
            );
        }

        return self::unknown([ReasonCode::InsufficientEvidence]);
    }

    /**
     * @param  list<ReasonCode>  $reasonCodes
     */
    private static function unknown(array $reasonCodes): VerdictResult
    {
        return VerdictResult::make(
            status: VerdictStatus::Unknown,
            hasSufficientPositiveEvidence: false,
            hasBlockingRisk: false,
            reasonCodes: $reasonCodes,
        );
    }

    /**
     * @param  list<Evidence>  $evidenceItems
     */
    private static function containsKind(array $evidenceItems, EvidenceKind $kind): bool
    {
        foreach ($evidenceItems as $evidence) {
            if ($evidence->kind === $kind) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Evidence>  $evidenceItems
     */
    private static function guardEvidenceItems(array $evidenceItems): void
    {
        foreach ($evidenceItems as $evidence) {
            if (! $evidence instanceof Evidence) {
                throw new InvalidArgumentException('Verdict evidence items must be Evidence values.');
            }
        }
    }

    /**
     * @param  list<Evidence>  $evidenceItems
     * @param  callable(Evidence): bool  $callback
     * @return list<Evidence>
     */
    private static function filter(array $evidenceItems, callable $callback): array
    {
        return array_values(array_filter($evidenceItems, $callback));
    }
}
