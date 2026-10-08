<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IngredientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('canonical_name'),
                TextEntry::make('slug'),
                TextEntry::make('origin'),
                IconEntry::make('requires_origin_evidence')
                    ->boolean(),
                IconEntry::make('requires_process_evidence')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
