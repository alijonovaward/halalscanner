<?php

namespace App\Filament\Resources\Rules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name'),
                Select::make('ingredient_id')
                    ->relationship('ingredient', 'id'),
                TextInput::make('scope')
                    ->required(),
                TextInput::make('madhhab')
                    ->required()
                    ->default('hanafi'),
                TextInput::make('public_verdict_effect')
                    ->required(),
                TextInput::make('fiqh_classification'),
                TextInput::make('reason_code')
                    ->required(),
                Toggle::make('requires_origin_evidence')
                    ->required(),
                Toggle::make('requires_process_evidence')
                    ->required(),
                Toggle::make('requires_scholar_review')
                    ->required(),
                Toggle::make('requires_editor_review')
                    ->required(),
                TextInput::make('review_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('source_reference'),
                Textarea::make('explanation_template')
                    ->columnSpanFull(),
            ]);
    }
}
