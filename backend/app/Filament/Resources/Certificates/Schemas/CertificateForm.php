<?php

namespace App\Filament\Resources\Certificates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required(),
                Select::make('certification_body_id')
                    ->relationship('certificationBody', 'name')
                    ->required(),
                TextInput::make('certificate_number')
                    ->required(),
                TextInput::make('scope')
                    ->required()
                    ->default('product'),
                TextInput::make('status')
                    ->required()
                    ->default('unknown'),
                DatePicker::make('valid_from'),
                DatePicker::make('valid_until'),
                DateTimePicker::make('verified_at'),
                TextInput::make('source_url')
                    ->url(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
