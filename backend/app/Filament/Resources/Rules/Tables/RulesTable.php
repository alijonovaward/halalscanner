<?php

namespace App\Filament\Resources\Rules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->searchable(),
                TextColumn::make('ingredient.id')
                    ->searchable(),
                TextColumn::make('scope')
                    ->searchable(),
                TextColumn::make('madhhab')
                    ->searchable(),
                TextColumn::make('public_verdict_effect')
                    ->searchable(),
                TextColumn::make('fiqh_classification')
                    ->searchable(),
                TextColumn::make('reason_code')
                    ->searchable(),
                IconColumn::make('requires_origin_evidence')
                    ->boolean(),
                IconColumn::make('requires_process_evidence')
                    ->boolean(),
                IconColumn::make('requires_scholar_review')
                    ->boolean(),
                IconColumn::make('requires_editor_review')
                    ->boolean(),
                TextColumn::make('review_status')
                    ->searchable(),
                TextColumn::make('source_reference')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
