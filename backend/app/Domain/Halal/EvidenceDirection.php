<?php

namespace App\Domain\Halal;

enum EvidenceDirection: string
{
    case SupportsHalal = 'supports_halal';
    case BlocksHalal = 'blocks_halal';
}
