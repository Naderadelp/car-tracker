<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Car;
use App\Models\Document;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DocumentForm
{
    /**
     * Options built from Document::TYPES, which is also what
     * App\Http\Requests\Document\StoreDocumentRequest validates against.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return collect(Document::TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => Str::headline($type)])
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('user_id')
                    ->label('Owner')
                    ->relationship('user', 'name')
                    ->searchable(['name', 'email'])
                    ->preload(),
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
                    ->searchable(),
                Select::make('type')
                    ->options(self::typeOptions())
                    ->required()
                    ->native(false),
                // StoreDocumentRequest enforces `after:today`; existing rows may
                // already be expired, so the admin panel does not block past dates.
                DatePicker::make('expiry_date')
                    ->label('Expiry date'),
                SpatieMediaLibraryFileUpload::make('document_file')
                    ->label('Document file')
                    ->collection('vehicle_documents')
                    ->disk('local')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->downloadable()
                    ->columnSpanFull(),
            ]);
    }
}
