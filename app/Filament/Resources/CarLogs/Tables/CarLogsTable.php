<?php

namespace App\Filament\Resources\CarLogs\Tables;

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

class CarLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('performed_at', 'desc')
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
                TextColumn::make('service_id')
                    ->label('Service')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('odometer_at_service')
                    ->label('Odometer')
                    ->numeric()
                    ->suffix(' km')
                    ->sortable(),
                TextColumn::make('actual_cost')
                    ->label('Cost')
                    ->money('EGP')
                    ->sortable()
                    ->summarize(Sum::make()->money('EGP')),
                TextColumn::make('performed_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('car_id')
                    ->label('Car')
                    ->relationship('car', 'id')
                    ->searchable(),
                Filter::make('performed_at')
                    ->schema([
                        DatePicker::make('from')->label('Performed from'),
                        DatePicker::make('until')->label('Performed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('performed_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('performed_at', '<=', $date))),
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
