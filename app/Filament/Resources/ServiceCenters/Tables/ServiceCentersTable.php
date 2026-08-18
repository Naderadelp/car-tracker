<?php

namespace App\Filament\Resources\ServiceCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('address')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('open_at')
                    ->label('Opens')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('close_at')
                    ->label('Closes')
                    ->time('H:i')
                    ->sortable(),
                IconColumn::make('is_open')
                    ->label('Open now')
                    ->boolean(),
                TextColumn::make('lat')
                    ->label('Latitude')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lng')
                    ->label('Longitude')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
