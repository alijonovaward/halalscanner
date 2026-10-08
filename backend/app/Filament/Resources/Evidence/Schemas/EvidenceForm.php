<?php

namespace App\Filament\Resources\Evidence\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EvidenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name'),
                Select::make('ingredient_id')
                    ->relationship('ingredient', 'id'),
                Select::make('certificate_id')
                    ->relationship('certificate', 'id'),
                TextInput::make('source_type')
                    ->required(),
                TextInput::make('trust_tier')
                    ->required()
                    ->default('unreviewed'),
                TextInput::make('evidence_strength')
                    ->required()
                    ->default('inferred'),
                TextInput::make('direction')
                    ->required(),
                TextInput::make('review_status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('checked_at'),
                DateTimePicker::make('expires_at'),
                TextInput::make('source_reference'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
