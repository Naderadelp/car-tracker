<?php

namespace App\Filament\Resources\CarModels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CarModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('Nullable: the column is set to null when a brand is deleted.'),
                TextInput::make('name')
                    ->label('Model name')
                    ->required()
                    ->maxLength(100)
                    // Mirrors StoreCarModelRequest: unique per brand + model year.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('brand_id', $get('brand_id'))
                            ->where('model_year', $get('model_year')),
                    ),
                TextInput::make('model_year')
                    ->label('Model year')
                    ->required()
                    ->numeric()
                    ->rule('digits:4')
                    ->live(onBlur: true),
            ]);
    }
}
