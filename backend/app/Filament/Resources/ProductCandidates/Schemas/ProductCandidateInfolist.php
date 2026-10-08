<?php

namespace App\Filament\Resources\ProductCandidates\Schemas;

use App\Models\ProductCandidate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductCandidateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('barcode'),
                TextEntry::make('name'),
                TextEntry::make('brand')
                    ->placeholder('-'),
                TextEntry::make('ingredients_text')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('source_url')
                    ->url(fn (ProductCandidate $record): string => $record->source_url)
                    ->openUrlInNewTab(),
                TextEntry::make('source_modified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('source_dataset'),
                TextEntry::make('source_license'),
                TextEntry::make('market_tags')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('review_status')
                    ->badge(),
                TextEntry::make('data_quality_flags')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state)
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
