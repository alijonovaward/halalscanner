<?php

namespace App\Filament\Resources\CertificationBodies\Pages;

use App\Filament\Resources\CertificationBodies\CertificationBodyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCertificationBodies extends ListRecords
{
    protected static string $resource = CertificationBodyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
