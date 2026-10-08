<?php

namespace App\Filament\Resources\UserReports\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name'),
                Select::make('ingredient_id')
                    ->relationship('ingredient', 'id'),
                TextInput::make('report_type')
                    ->required(),
                TextInput::make('review_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('reason_code'),
                Textarea::make('submitted_evidence')
                    ->columnSpanFull(),
                TextInput::make('source_reference'),
                TextInput::make('reporter_contact_hash'),
                Textarea::make('reviewer_decision')
                    ->columnSpanFull(),
                DateTimePicker::make('reviewed_at'),
            ]);
    }
}
