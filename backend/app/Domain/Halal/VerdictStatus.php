<?php

namespace App\Domain\Halal;

enum VerdictStatus: string
{
    case Halal = 'halal';
    case Haram = 'haram';
    case Mushbooh = 'mushbooh';
    case Unknown = 'unknown';
}
