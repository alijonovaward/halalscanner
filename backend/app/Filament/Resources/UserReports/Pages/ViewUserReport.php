<?php

namespace App\Filament\Resources\UserReports\Pages;

use App\Filament\Resources\UserReports\UserReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserReport extends ViewRecord
{
    protected static string $resource = UserReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
