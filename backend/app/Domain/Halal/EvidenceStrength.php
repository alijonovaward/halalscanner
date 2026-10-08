<?php

namespace App\Domain\Halal;

enum EvidenceStrength: string
{
    case CertifiedSource = 'certified_source';
    case ExpertFatwa = 'expert_fatwa';
    case ExplicitText = 'explicit_text';
    case Inferred = 'inferred';
    case ManufacturerClaim = 'manufacturer_claim';
    case ScientificProcessEvidence = 'scientific_process_evidence';
    case UserReport = 'user_report';
}
