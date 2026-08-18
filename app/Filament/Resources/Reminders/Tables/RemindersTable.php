<?php

namespace App\Filament\Resources\Reminders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RemindersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
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
                TextColumn::make('title')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('remind_on')
                    ->label('Remind on')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('remind_at_km')
                    ->label('Remind at')
                    ->numeric()
                    ->suffix(' km')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('notified_at')
                    ->label('Notified')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Pending'),
            ])
            ->filters([
                SelectFilter::make('car_id')
                    ->label('Car')
                    ->relationship('car', 'id')
                    ->searchable(),
                Filter::make('pending')
                    ->label('Not yet notified')
                    ->query(fn (Builder $query): Builder => $query->whereNull('notified_at')),
                Filter::make('overdue')
                    ->label('Date passed')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('remind_on')
                        ->whereDate('remind_on', '<=', now())),
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
