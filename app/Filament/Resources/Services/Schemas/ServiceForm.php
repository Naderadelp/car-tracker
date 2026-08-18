<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Models\Car;
use App\Models\CarModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->description('A service with no owner is a catalogue entry shared by every car of that model.')
                    ->columns(2)
                    ->schema([
                        Select::make('car_model_id')
                            ->label('Car model')
                            ->options(fn (): array => CarModel::query()
                                ->with('brand')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (CarModel $model): array => [
                                    $model->id => trim(($model->brand?->name ?? '').' '.$model->name.' '.$model->model_year),
                                ])
                                ->all())
                            ->searchable()
                            ->helperText('Required for catalogue services.'),
                        Select::make('user_id')
                            ->label('Owner')
                            ->relationship('user', 'name')
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->helperText('Leave empty for a catalogue service.'),
                        Select::make('car_id')
                            ->label('Car')
                            ->options(fn (): array => Car::query()
                                ->with(['brand', 'carModel'])
                                ->orderByDesc('id')
                                ->limit(200)
                                ->get()
                                ->mapWithKeys(fn (Car $car): array => [
                                    $car->id => '#'.$car->id.' '.trim(($car->brand?->name ?? '').' '.($car->carModel?->name ?? '')),
                                ])
                                ->all())
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        TextInput::make('km')
                            ->label('Due at (km)')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('EGP'),
                        Select::make('items')
                            ->label('Items')
                            ->relationship('items', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
