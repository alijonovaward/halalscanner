<?php

namespace App\Filament\Resources\CertificationBodies\Pages;

use App\Filament\Resources\CertificationBodies\CertificationBodyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCertificationBody extends ViewRecord
{
    protected static string $resource = CertificationBodyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
