<?php

namespace App\Filament\Resources\ProductCandidates\Pages;

use App\Filament\Resources\ProductCandidates\ProductCandidateResource;
use Filament\Resources\Pages\ListRecords;

class ListProductCandidates extends ListRecords
{
    protected static string $resource = ProductCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
