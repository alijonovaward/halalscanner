<?php

namespace App\Domain\Halal;

enum TrustTier: string
{
    case Trusted = 'trusted';
    case Untrusted = 'untrusted';
    case Unreviewed = 'unreviewed';
}
