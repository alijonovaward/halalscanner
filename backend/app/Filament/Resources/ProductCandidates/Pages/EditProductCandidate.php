<?php

namespace App\Filament\Resources\ProductCandidates\Pages;

use App\Filament\Resources\ProductCandidates\ProductCandidateResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductCandidate extends EditRecord
{
    protected static string $resource = ProductCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
