<?php

namespace App\Filament\Resources\ServiceCenters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        Select::make('brand_id')
                            ->label('Brand')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('address')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('mobile')
                            ->tel()
                            ->required()
                            ->maxLength(50),
                    ]),
                Section::make('Opening hours')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('open_at')
                            ->label('Opens at')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('close_at')
                            ->label('Closes at')
                            ->seconds(false)
                            ->required()
                            ->after('open_at'),
                    ]),
                Section::make('Location')
                    ->columns(2)
                    ->schema([
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->required()
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.00000001),
                        TextInput::make('lng')
                            ->label('Longitude')
                            ->required()
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.00000001),
                    ]),
            ]);
    }
}
