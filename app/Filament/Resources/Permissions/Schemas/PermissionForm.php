<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(125)
                    ->unique(ignoreRecord: true)
                    ->helperText('Named {action}-{subject}, e.g. "index-car" or "destroy-fuel-price". Permissions are always stored under the "api" guard.'),
                Select::make('roles')
                    ->label('Granted to roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}
