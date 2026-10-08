<?php

namespace App\Filament\Resources\Rules\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('product.name')
                    ->label('Product')
                    ->placeholder('-'),
                TextEntry::make('ingredient.id')
                    ->label('Ingredient')
                    ->placeholder('-'),
                TextEntry::make('scope'),
                TextEntry::make('madhhab'),
                TextEntry::make('public_verdict_effect'),
                TextEntry::make('fiqh_classification')
                    ->placeholder('-'),
                TextEntry::make('reason_code'),
                IconEntry::make('requires_origin_evidence')
                    ->boolean(),
                IconEntry::make('requires_process_evidence')
                    ->boolean(),
                IconEntry::make('requires_scholar_review')
                    ->boolean(),
                IconEntry::make('requires_editor_review')
                    ->boolean(),
                TextEntry::make('review_status'),
                TextEntry::make('source_reference')
                    ->placeholder('-'),
                TextEntry::make('explanation_template')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
