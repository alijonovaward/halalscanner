<?php

namespace App\Domain\Halal;

enum SourceType: string
{
    case Certificate = 'certificate';
    case ExpertRule = 'expert_rule';
    case IngredientDatabase = 'ingredient_database';
    case LabReport = 'lab_report';
    case ManufacturerClaim = 'manufacturer_claim';
    case ManualReview = 'manual_review';
    case PackageHalalClaim = 'package_halal_claim';
    case UserReport = 'user_report';
}
