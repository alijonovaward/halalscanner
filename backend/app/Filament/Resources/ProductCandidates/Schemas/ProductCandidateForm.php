<?php

namespace App\Filament\Resources\ProductCandidates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('barcode')
                    ->disabled()
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('brand'),
                Textarea::make('ingredients_text')
                    ->columnSpanFull(),
                Select::make('review_status')
                    ->options([
                        'pending_review' => 'Pending review',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending_review'),
            ]);
    }
}
