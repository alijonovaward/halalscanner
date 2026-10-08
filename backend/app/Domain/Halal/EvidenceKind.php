<?php

namespace App\Domain\Halal;

enum EvidenceKind: string
{
    case HalalCertificate = 'halal_certificate';
    case PackageHalalClaim = 'package_halal_claim';
    case UserReport = 'user_report';
    case ExpertRule = 'expert_rule';
}
