<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('km')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('carModel.name')
                    ->label('Car model')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Catalogue'),
                TextColumn::make('car_id')
                    ->label('Car')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('km')
                    ->label('Due at')
                    ->numeric()
                    ->suffix(' km')
                    ->sortable(),
                TextColumn::make('price')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                IconColumn::make('is_catalogue')
                    ->label('Catalogue')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('car_model_id')
                    ->label('Car model')
                    ->relationship('carModel', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('catalogue_only')
                    ->label('Catalogue services only')
                    ->query(fn (Builder $query): Builder => $query->whereNull('user_id')),
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
