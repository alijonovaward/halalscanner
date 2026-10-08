<?php

namespace App\Services\Halal;

use App\Domain\Halal\Evidence as DomainEvidence;
use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceKind;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Domain\Halal\VerdictCalculator;
use App\Domain\Halal\VerdictResult;
use App\Models\Evidence as EvidenceModel;
use App\Models\Product;

class ProductVerdictService
{
    public function evaluate(Product $product): VerdictResult
    {
        $product->loadMissing('evidence.certificate');

        $domainEvidence = $product->evidence
            ->map(fn (EvidenceModel $evidence): DomainEvidence => $this->mapEvidence($evidence))
            ->values()
            ->all();

        return VerdictCalculator::fromEvidence($domainEvidence);
    }

    private function mapEvidence(EvidenceModel $evidence): DomainEvidence
    {
        $isTrusted = $evidence->trust_tier === TrustTier::Trusted
            && $evidence->review_status === ReviewStatus::Approved;

        return DomainEvidence::fromRegistry(
            kind: $this->mapKind($evidence->source_type),
            direction: $evidence->direction,
            isTrusted: $isTrusted,
            isSufficientForHalal: $this->isSufficientForHalal($evidence, $isTrusted),
        );
    }

    private function mapKind(SourceType $sourceType): EvidenceKind
    {
        return match ($sourceType) {
            SourceType::Certificate => EvidenceKind::HalalCertificate,
            SourceType::PackageHalalClaim,
            SourceType::ManufacturerClaim => EvidenceKind::PackageHalalClaim,
            SourceType::UserReport => EvidenceKind::UserReport,
            default => EvidenceKind::ExpertRule,
        };
    }

    private function isSufficientForHalal(EvidenceModel $evidence, bool $isTrusted): bool
    {
        if (! $isTrusted || $evidence->direction !== EvidenceDirection::SupportsHalal) {
            return false;
        }

        if ($evidence->source_type === SourceType::Certificate && ! $this->certificateAllowsHalal($evidence)) {
            return false;
        }

        return in_array($evidence->evidence_strength, [
            EvidenceStrength::CertifiedSource,
            EvidenceStrength::ExpertFatwa,
            EvidenceStrength::ExplicitText,
            EvidenceStrength::ScientificProcessEvidence,
        ], true);
    }

    private function certificateAllowsHalal(EvidenceModel $evidence): bool
    {
        $certificate = $evidence->certificate;

        if ($certificate === null) {
            return false;
        }

        return $certificate->product_id === $evidence->product_id
            && $certificate->status === 'valid'
            && $certificate->scope === 'product'
            && $certificate->verified_at !== null
            && ($certificate->valid_from === null || ! $certificate->valid_from->isAfter(today()))
            && $certificate->valid_until !== null
            && ! $certificate->valid_until->isBefore(today());
    }
}
