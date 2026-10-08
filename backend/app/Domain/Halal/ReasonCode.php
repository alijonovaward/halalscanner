<?php

namespace App\Domain\Halal;

enum ReasonCode: string
{
    case AnimalOriginUnclear = 'animal_origin_unclear';
    case CertificateMissing = 'certificate_missing';
    case CertificateUntrusted = 'certificate_untrusted';
    case CrossContaminationRisk = 'cross_contamination_risk';
    case InsufficientEvidence = 'insufficient_evidence';
    case BlockingRisk = 'blocking_risk';
    case LowOcrQuality = 'low_ocr_quality';
    case MadhhabDisagreement = 'madhhab_disagreement';
    case MakruhTanzihi = 'makruh_tanzihi';
    case MakruhTahrimi = 'makruh_tahrimi';
    case ProcessUnclear = 'process_unclear';
    case SourceConflict = 'source_conflict';
    case TraceAmountUnclear = 'trace_amount_unclear';
    case UnknownOrigin = 'unknown_origin';
    case UnknownProduct = 'unknown_product';
}
