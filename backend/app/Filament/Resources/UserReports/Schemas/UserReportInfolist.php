<?php

namespace App\Filament\Resources\UserReports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserReportInfolist
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
                TextEntry::make('report_type'),
                TextEntry::make('review_status'),
                TextEntry::make('reason_code')
                    ->placeholder('-'),
                TextEntry::make('submitted_evidence')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('source_reference')
                    ->placeholder('-'),
                TextEntry::make('reporter_contact_hash')
                    ->placeholder('-'),
                TextEntry::make('reviewer_decision')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('reviewed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
