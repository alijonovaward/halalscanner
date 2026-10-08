<?php

namespace App\Filament\Resources\Evidence\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EvidenceInfolist
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
                TextEntry::make('certificate.id')
                    ->label('Certificate')
                    ->placeholder('-'),
                TextEntry::make('source_type'),
                TextEntry::make('trust_tier'),
                TextEntry::make('evidence_strength'),
                TextEntry::make('direction'),
                TextEntry::make('review_status'),
                TextEntry::make('checked_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('expires_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('source_reference')
                    ->placeholder('-'),
                TextEntry::make('notes')
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
