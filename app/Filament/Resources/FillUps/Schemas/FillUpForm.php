<?php

namespace App\Filament\Resources\FillUps\Schemas;

use App\Models\Car;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FillUpForm
{
    /**
     * Mirrors the `fuel_type` enum on fill_ups and the `in:92,95,electric`
     * rule in App\Http\Requests\FillUp\QuickFillUpRequest.
     */
    public const FUEL_TYPES = [
        '92' => '92 octane',
        '95' => '95 octane',
        'electric' => 'Electric',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fill-up')
                    ->columns(2)
                    ->schema([
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
                        Select::make('fuel_type')
                            ->label('Fuel type')
                            ->options(self::FUEL_TYPES)
                            ->native(false),
                        DatePicker::make('fill_date')
                            ->label('Fill date')
                            ->required()
                            ->maxDate(now())
                            ->default(now()),
                        TextInput::make('odometer')
                            ->label('Odometer (km)')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                    ]),
                Section::make('Amounts')
                    ->columns(2)
                    ->schema([
                        TextInput::make('liters')
                            ->label('Litres')
                            ->numeric()
                            ->minValue(0.1)
                            ->step(0.01)
                            ->helperText('Nullable: a quick fill-up may record only the amount paid.'),
                        TextInput::make('tank_percentage')
                            ->label('Tank filled (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        TextInput::make('cost_egp')
                            ->label('Cost')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('EGP'),
                    ]),
                Section::make('Station location')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('station_lat')
                            ->label('Latitude')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.00000001),
                        TextInput::make('station_lng')
                            ->label('Longitude')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.00000001),
                    ]),
            ]);
    }
}
