<?php

namespace App\Filament\Resources\Cars\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('carModel.name')
                    ->label('Model')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('current_km')
                    ->label('Odometer')
                    ->numeric()
                    ->suffix(' km')
                    ->sortable(),
                TextColumn::make('tank_size')
                    ->label('Tank')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' L')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('has_warranty')
                    ->label('Warranty')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('warranty_expiry_date')
                    ->label('Warranty until')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('has_warranty')
                    ->label('Warranty'),
                Filter::make('missing_tank_size')
                    ->label('Tank size not set')
                    ->query(fn (Builder $query): Builder => $query->whereNull('tank_size')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
