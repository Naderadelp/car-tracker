<?php

namespace App\Filament\Resources\FuelPrices\Tables;

use App\Filament\Resources\FuelPrices\Schemas\FuelPriceForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FuelPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FuelPriceForm::TYPES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        '92' => 'warning',
                        '95' => 'success',
                        'electric' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('price_per_unit')
                    ->label('Price')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('effective_from')
                    ->label('Effective from')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(FuelPriceForm::TYPES),
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
