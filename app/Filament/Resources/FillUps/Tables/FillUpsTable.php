<?php

namespace App\Filament\Resources\FillUps\Tables;

use App\Filament\Resources\FillUps\Schemas\FillUpForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FillUpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('fill_date', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('car_id')
                    ->label('Car')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('car.user.name')
                    ->label('Owner')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('fill_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('fuel_type')
                    ->label('Fuel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FillUpForm::FUEL_TYPES[$state] ?? '—')
                    ->color(fn (?string $state): string => match ($state) {
                        '92' => 'warning',
                        '95' => 'success',
                        'electric' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('liters')
                    ->label('Litres')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->placeholder('—')
                    ->summarize(Sum::make()->numeric(decimalPlaces: 2)),
                TextColumn::make('tank_percentage')
                    ->label('Tank %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('odometer')
                    ->numeric()
                    ->suffix(' km')
                    ->sortable(),
                TextColumn::make('cost_egp')
                    ->label('Cost')
                    ->money('EGP')
                    ->sortable()
                    ->summarize(Sum::make()->money('EGP')),
            ])
            ->filters([
                SelectFilter::make('fuel_type')
                    ->label('Fuel type')
                    ->options(FillUpForm::FUEL_TYPES),
                SelectFilter::make('car_id')
                    ->label('Car')
                    ->relationship('car', 'id')
                    ->searchable(),
                Filter::make('fill_date')
                    ->schema([
                        DatePicker::make('from')->label('Filled from'),
                        DatePicker::make('until')->label('Filled until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('fill_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('fill_date', '<=', $date))),
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
