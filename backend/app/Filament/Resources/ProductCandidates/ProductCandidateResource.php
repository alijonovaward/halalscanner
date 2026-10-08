<?php

namespace App\Filament\Resources\ProductCandidates;

use App\Filament\Resources\ProductCandidates\Pages\EditProductCandidate;
use App\Filament\Resources\ProductCandidates\Pages\ListProductCandidates;
use App\Filament\Resources\ProductCandidates\Pages\ViewProductCandidate;
use App\Filament\Resources\ProductCandidates\Schemas\ProductCandidateForm;
use App\Filament\Resources\ProductCandidates\Schemas\ProductCandidateInfolist;
use App\Filament\Resources\ProductCandidates\Tables\ProductCandidatesTable;
use App\Models\ProductCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductCandidateResource extends Resource
{
    protected static ?string $model = ProductCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductCandidateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductCandidateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCandidatesTable::configure($table);
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
            'index' => ListProductCandidates::route('/'),
            'view' => ViewProductCandidate::route('/{record}'),
            'edit' => EditProductCandidate::route('/{record}/edit'),
        ];
    }
}
