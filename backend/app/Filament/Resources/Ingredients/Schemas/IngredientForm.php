<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('canonical_name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('origin')
                    ->required()
                    ->default('unknown'),
                Toggle::make('requires_origin_evidence')
                    ->required(),
                Toggle::make('requires_process_evidence')
                    ->required(),
            ]);
    }
}
