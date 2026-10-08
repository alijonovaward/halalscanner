<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Services\Halal\ProductVerdictPreview;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verdict preview')
                    ->columns(4)
                    ->components([
                        TextEntry::make('verdict_preview.status')
                            ->label('Status')
                            ->badge()
                            ->state(fn (Product $record): string => self::preview($record)['status']),
                        TextEntry::make('verdict_preview.reason_codes')
                            ->label('Reason codes')
                            ->state(fn (Product $record): string => self::preview($record)['reason_codes']),
                        TextEntry::make('verdict_preview.has_sufficient_positive_evidence')
                            ->label('Sufficient evidence')
                            ->state(fn (Product $record): string => self::preview($record)['has_sufficient_positive_evidence']),
                        TextEntry::make('verdict_preview.has_blocking_risk')
                            ->label('Blocking risk')
                            ->state(fn (Product $record): string => self::preview($record)['has_blocking_risk']),
                    ])
                    ->columnSpanFull(),
                TextEntry::make('barcode')
                    ->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('brand')
                    ->placeholder('-'),
                TextEntry::make('manufacturer')
                    ->placeholder('-'),
                TextEntry::make('country_code')
                    ->placeholder('-'),
                TextEntry::make('ingredients_text')
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

    /**
     * @return array{
     *     status: string,
     *     reason_codes: string,
     *     has_sufficient_positive_evidence: string,
     *     has_blocking_risk: string
     * }
     */
    private static function preview(Product $product): array
    {
        return app(ProductVerdictPreview::class)->forProduct($product);
    }
}
