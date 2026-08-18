<?php

namespace App\Filament\Resources\Cars\Schemas;

use App\Models\CarModel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ownership')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Owner')
                            ->relationship('user', 'name')
                            ->searchable(['name', 'email'])
                            ->preload(),
                        Select::make('brand_id')
                            ->label('Brand')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('car_model_id', null)),
                        Select::make('car_model_id')
                            ->label('Model')
                            ->options(fn (Get $get): array => CarModel::query()
                                ->when($get('brand_id'), fn ($query, $brandId) => $query->where('brand_id', $brandId))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (CarModel $model): array => [
                                    $model->id => trim($model->name.' '.$model->model_year),
                                ])
                                ->all())
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Vehicle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('current_km')
                            ->label('Current odometer (km)')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        // Mirrors RegisterRequest / UpdateProfileRequest: numeric, 0.1 - 999.
                        TextInput::make('tank_size')
                            ->label('Tank size (litres)')
                            ->numeric()
                            ->minValue(0.1)
                            ->maxValue(999)
                            ->step(0.01),
                    ]),
                Section::make('Warranty')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_warranty')
                            ->label('Has warranty')
                            ->live()
                            ->default(false)
                            ->columnSpanFull(),
                        TextInput::make('warranty_limit_km')
                            ->label('Warranty limit (km)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->requiredIf('has_warranty', true)
                            ->visible(fn (Get $get): bool => (bool) $get('has_warranty')),
                        DatePicker::make('warranty_expiry_date')
                            ->label('Warranty expiry')
                            ->requiredIf('has_warranty', true)
                            ->visible(fn (Get $get): bool => (bool) $get('has_warranty')),
                    ]),
            ]);
    }
}
