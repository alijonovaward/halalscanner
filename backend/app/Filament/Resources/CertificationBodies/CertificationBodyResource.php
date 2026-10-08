<?php

namespace App\Filament\Resources\CertificationBodies;

use App\Filament\Resources\CertificationBodies\Pages\CreateCertificationBody;
use App\Filament\Resources\CertificationBodies\Pages\EditCertificationBody;
use App\Filament\Resources\CertificationBodies\Pages\ListCertificationBodies;
use App\Filament\Resources\CertificationBodies\Pages\ViewCertificationBody;
use App\Filament\Resources\CertificationBodies\Schemas\CertificationBodyForm;
use App\Filament\Resources\CertificationBodies\Schemas\CertificationBodyInfolist;
use App\Filament\Resources\CertificationBodies\Tables\CertificationBodiesTable;
use App\Models\CertificationBody;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CertificationBodyResource extends Resource
{
    protected static ?string $model = CertificationBody::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CertificationBodyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CertificationBodyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CertificationBodiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificationBodies::route('/'),
            'create' => CreateCertificationBody::route('/create'),
            'view' => ViewCertificationBody::route('/{record}'),
            'edit' => EditCertificationBody::route('/{record}/edit'),
        ];
    }
}
