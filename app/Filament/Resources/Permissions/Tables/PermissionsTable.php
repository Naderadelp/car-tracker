<?php

namespace App\Filament\Resources\Permissions\Tables;

use App\Filament\Support\PermissionGroups;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->badge()
                    ->color('gray')
                    ->state(fn ($record): string => PermissionGroups::groupLabel(PermissionGroups::subjectFor($record->name))),
                TextColumn::make('action')
                    ->label('Action')
                    ->state(fn ($record): string => PermissionGroups::actionLabelFor($record->name)),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('roles_count')
                    ->label('Roles')
                    ->counts('roles')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->label('Subject')
                    ->options(fn (): array => collect(PermissionGroups::SUBJECTS)
                        ->sort()
                        ->mapWithKeys(fn (string $subject): array => [$subject => PermissionGroups::groupLabel($subject)])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $subject = $data['value'] ?? null;

                        if (blank($subject)) {
                            return $query;
                        }

                        // Exclude names that belong to a longer, more specific
                        // subject (e.g. "car" must not match "index-car-log").
                        $moreSpecific = collect(PermissionGroups::SUBJECTS)
                            ->filter(fn (string $other): bool => $other !== $subject && str_ends_with($other, $subject));

                        return $query
                            ->where(fn (Builder $q) => $q
                                ->where('name', $subject)
                                ->orWhere('name', 'like', '%-'.$subject))
                            ->when(
                                $moreSpecific->isNotEmpty(),
                                fn (Builder $q) => $moreSpecific->each(fn (string $other) => $q
                                    ->where('name', 'not like', '%'.$other)),
                            );
                    }),
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->multiple()
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
