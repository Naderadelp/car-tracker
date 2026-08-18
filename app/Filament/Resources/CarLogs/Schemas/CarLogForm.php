<?php

namespace App\Filament\Resources\CarLogs\Schemas;

use App\Models\Car;
use App\Models\Service;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CarLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('car_id')
                    ->label('Car')
                    ->options(fn (): array => Car::query()
                        ->with(['user', 'brand', 'carModel'])
                        ->orderByDesc('id')
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Car $car): array => [
                            $car->id => '#'.$car->id.' '.trim(($car->brand?->name ?? '').' '.($car->carModel?->name ?? '')).' — '.($car->user?->name ?? 'unassigned'),
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('service_id')
                    ->label('Service')
                    ->options(fn (): array => Service::query()
                        ->with('carModel')
                        ->orderByDesc('id')
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Service $service): array => [
                            $service->id => '#'.$service->id.' '.($service->carModel?->name ?? 'custom').' @ '.$service->km.' km',
                        ])
                        ->all())
                    ->searchable(),
                TextInput::make('odometer_at_service')
                    ->label('Odometer at service (km)')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0),
                TextInput::make('actual_cost')
                    ->label('Actual cost')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('EGP'),
                DatePicker::make('performed_at')
                    ->label('Performed at')
                    ->required()
                    ->maxDate(now())
                    ->default(now()),
            ]);
    }
}
