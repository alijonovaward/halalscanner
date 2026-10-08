<?php

namespace App\Filament\Resources\ProductCandidates\Pages;

use App\Filament\Resources\ProductCandidates\ProductCandidateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductCandidate extends ViewRecord
{
    protected static string $resource = ProductCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
