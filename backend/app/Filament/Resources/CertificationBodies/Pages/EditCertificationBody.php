<?php

namespace App\Filament\Resources\CertificationBodies\Pages;

use App\Filament\Resources\CertificationBodies\CertificationBodyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCertificationBody extends EditRecord
{
    protected static string $resource = CertificationBodyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
