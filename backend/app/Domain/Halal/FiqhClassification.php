<?php

namespace App\Domain\Halal;

enum FiqhClassification: string
{
    case Halal = 'halal';
    case Haram = 'haram';
    case MakruhTanzihi = 'makruh_tanzihi';
    case MakruhTahrimi = 'makruh_tahrimi';
}
