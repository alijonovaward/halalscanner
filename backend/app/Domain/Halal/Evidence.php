<?php

namespace App\Domain\Halal;

final readonly class Evidence
{
    private function __construct(
        public EvidenceKind $kind,
        public EvidenceDirection $direction,
        public bool $isTrusted,
        public bool $isSufficientForHalal,
    ) {}

    public static function fromRegistry(
        EvidenceKind $kind,
        EvidenceDirection $direction,
        bool $isTrusted,
        bool $isSufficientForHalal,
    ): self {
        return new self(
            kind: $kind,
            direction: $direction,
            isTrusted: $isTrusted,
            isSufficientForHalal: $isSufficientForHalal,
        );
    }

    public static function trustedHalalCertificate(): self
    {
        return new self(
            kind: EvidenceKind::HalalCertificate,
            direction: EvidenceDirection::SupportsHalal,
            isTrusted: true,
            isSufficientForHalal: true,
        );
    }

    public static function unverifiedPackageHalalClaim(): self
    {
        return new self(
            kind: EvidenceKind::PackageHalalClaim,
            direction: EvidenceDirection::SupportsHalal,
            isTrusted: false,
            isSufficientForHalal: false,
        );
    }

    public static function userHalalReport(): self
    {
        return new self(
            kind: EvidenceKind::UserReport,
            direction: EvidenceDirection::SupportsHalal,
            isTrusted: false,
            isSufficientForHalal: false,
        );
    }

    public static function trustedHaramRule(): self
    {
        return new self(
            kind: EvidenceKind::ExpertRule,
            direction: EvidenceDirection::BlocksHalal,
            isTrusted: true,
            isSufficientForHalal: false,
        );
    }

    public function supportsHalal(): bool
    {
        return $this->direction === EvidenceDirection::SupportsHalal;
    }

    public function blocksHalal(): bool
    {
        return $this->direction === EvidenceDirection::BlocksHalal;
    }
}
