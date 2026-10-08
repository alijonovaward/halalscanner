<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('barcode'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('brand'),
                TextInput::make('manufacturer'),
                TextInput::make('country_code'),
                Textarea::make('ingredients_text')
                    ->columnSpanFull(),
            ]);
    }
}
