<?php

namespace App\Filament\Resources\Evidence;

use App\Filament\Resources\Evidence\Pages\CreateEvidence;
use App\Filament\Resources\Evidence\Pages\EditEvidence;
use App\Filament\Resources\Evidence\Pages\ListEvidence;
use App\Filament\Resources\Evidence\Pages\ViewEvidence;
use App\Filament\Resources\Evidence\Schemas\EvidenceForm;
use App\Filament\Resources\Evidence\Schemas\EvidenceInfolist;
use App\Filament\Resources\Evidence\Tables\EvidenceTable;
use App\Models\Evidence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EvidenceResource extends Resource
{
    protected static ?string $model = Evidence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'source_reference';

    public static function form(Schema $schema): Schema
    {
        return EvidenceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EvidenceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EvidenceTable::configure($table);
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
            'index' => ListEvidence::route('/'),
            'create' => CreateEvidence::route('/create'),
            'view' => ViewEvidence::route('/{record}'),
            'edit' => EditEvidence::route('/{record}/edit'),
        ];
    }
}
