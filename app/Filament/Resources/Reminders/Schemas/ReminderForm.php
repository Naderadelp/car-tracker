<?php

namespace App\Filament\Resources\Reminders\Schemas;

use App\Models\Car;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReminderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reminder')
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
                        TextInput::make('title')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Trigger')
                    // StoreReminderRequest requires at least one of the two.
                    ->description('Set a date, a target odometer reading, or both. At least one is required.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('remind_on')
                            ->label('Remind on')
                            ->requiredWithout('remind_at_km')
                            ->live(onBlur: true),
                        TextInput::make('remind_at_km')
                            ->label('Remind at (km)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->requiredWithout('remind_on')
                            ->live(onBlur: true),
                        DateTimePicker::make('notified_at')
                            ->label('Notified at')
                            ->helperText('Set automatically when the push notification is sent. Clear it to re-send.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
