<?php

namespace App\Domain\Halal;

use InvalidArgumentException;

final readonly class VerdictResult
{
    /**
     * @param  list<ReasonCode>  $reasonCodes
     */
    private function __construct(
        public VerdictStatus $status,
        public bool $hasSufficientPositiveEvidence,
        public bool $hasBlockingRisk,
        public array $reasonCodes,
    ) {}

    /**
     * @param  list<ReasonCode>  $reasonCodes
     */
    public static function make(
        VerdictStatus $status,
        bool $hasSufficientPositiveEvidence,
        bool $hasBlockingRisk,
        array $reasonCodes,
    ): self {
        self::guardAgainstFalseHalal(
            status: $status,
            hasSufficientPositiveEvidence: $hasSufficientPositiveEvidence,
            hasBlockingRisk: $hasBlockingRisk,
        );

        self::guardReasonCodes($reasonCodes);

        return new self(
            status: $status,
            hasSufficientPositiveEvidence: $hasSufficientPositiveEvidence,
            hasBlockingRisk: $hasBlockingRisk,
            reasonCodes: array_values($reasonCodes),
        );
    }

    private static function guardAgainstFalseHalal(
        VerdictStatus $status,
        bool $hasSufficientPositiveEvidence,
        bool $hasBlockingRisk,
    ): void {
        if ($status !== VerdictStatus::Halal) {
            return;
        }

        if (! $hasSufficientPositiveEvidence) {
            throw new InvalidArgumentException('A halal verdict requires sufficient positive evidence.');
        }

        if ($hasBlockingRisk) {
            throw new InvalidArgumentException('A halal verdict cannot be issued while a blocking risk is unresolved.');
        }
    }

    /**
     * @param  list<ReasonCode>  $reasonCodes
     */
    private static function guardReasonCodes(array $reasonCodes): void
    {
        foreach ($reasonCodes as $reasonCode) {
            if (! $reasonCode instanceof ReasonCode) {
                throw new InvalidArgumentException('Verdict reason codes must be ReasonCode values.');
            }
        }
    }
}
