<?php

namespace App\Filament\Resources\Evidence\Pages;

use App\Filament\Resources\Evidence\EvidenceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEvidence extends ViewRecord
{
    protected static string $resource = EvidenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
