<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    private array $data = [
        'Toyota' => [
            ['name' => 'Camry',        'model_year' => 2024],
            ['name' => 'Corolla',      'model_year' => 2024],
            ['name' => 'RAV4',         'model_year' => 2024],
            ['name' => 'Land Cruiser', 'model_year' => 2024],
            ['name' => 'Hilux',        'model_year' => 2023],
            ['name' => 'Prius',        'model_year' => 2024],
            ['name' => 'Yaris',        'model_year' => 2023],
            ['name' => 'Fortuner',     'model_year' => 2023],
        ],
        'Honda' => [
            ['name' => 'Civic',  'model_year' => 2024],
            ['name' => 'Accord', 'model_year' => 2024],
            ['name' => 'CR-V',   'model_year' => 2024],
            ['name' => 'HR-V',   'model_year' => 2023],
            ['name' => 'Pilot',  'model_year' => 2024],
            ['name' => 'Jazz',   'model_year' => 2023],
        ],
        'Nissan' => [
            ['name' => 'Altima',  'model_year' => 2024],
            ['name' => 'Maxima',  'model_year' => 2023],
            ['name' => 'Patrol',  'model_year' => 2024],
            ['name' => 'X-Trail', 'model_year' => 2024],
            ['name' => 'Navara',  'model_year' => 2023],
            ['name' => 'Sunny',   'model_year' => 2022],
            ['name' => 'Tiida',   'model_year' => 2022],
        ],
        'Hyundai' => [
            ['name' => 'Elantra',   'model_year' => 2024],
            ['name' => 'Tucson',    'model_year' => 2024],
            ['name' => 'Santa Fe',  'model_year' => 2024],
            ['name' => 'Sonata',    'model_year' => 2023],
            ['name' => 'i10',       'model_year' => 2024],
            ['name' => 'i20',       'model_year' => 2023],
            ['name' => 'Creta',     'model_year' => 2024],
        ],
        'Kia' => [
            ['name' => 'Cerato',   'model_year' => 2024],
            ['name' => 'Sportage', 'model_year' => 2024],
            ['name' => 'Sorento',  'model_year' => 2024],
            ['name' => 'Picanto',  'model_year' => 2023],
            ['name' => 'Stinger',  'model_year' => 2023],
            ['name' => 'Carnival', 'model_year' => 2024],
        ],
        'BMW' => [
            ['name' => '3 Series', 'model_year' => 2024],
            ['name' => '5 Series', 'model_year' => 2024],
            ['name' => '7 Series', 'model_year' => 2024],
            ['name' => 'X3',       'model_year' => 2024],
            ['name' => 'X5',       'model_year' => 2024],
            ['name' => 'X6',       'model_year' => 2023],
            ['name' => 'M3',       'model_year' => 2024],
            ['name' => 'M5',       'model_year' => 2024],
        ],
        'Mercedes-Benz' => [
            ['name' => 'C-Class', 'model_year' => 2024],
            ['name' => 'E-Class', 'model_year' => 2024],
            ['name' => 'S-Class', 'model_year' => 2024],
            ['name' => 'GLC',     'model_year' => 2024],
            ['name' => 'GLE',     'model_year' => 2024],
            ['name' => 'GLS',     'model_year' => 2023],
            ['name' => 'A-Class', 'model_year' => 2023],
        ],
        'Audi' => [
            ['name' => 'A3',  'model_year' => 2024],
            ['name' => 'A4',  'model_year' => 2024],
            ['name' => 'A6',  'model_year' => 2024],
            ['name' => 'Q3',  'model_year' => 2024],
            ['name' => 'Q5',  'model_year' => 2024],
            ['name' => 'Q7',  'model_year' => 2023],
            ['name' => 'RS4', 'model_year' => 2024],
        ],
        'Volkswagen' => [
            ['name' => 'Golf',     'model_year' => 2024],
            ['name' => 'Passat',   'model_year' => 2024],
            ['name' => 'Tiguan',   'model_year' => 2024],
            ['name' => 'Touareg',  'model_year' => 2023],
            ['name' => 'Polo',     'model_year' => 2024],
            ['name' => 'Arteon',   'model_year' => 2023],
        ],
        'Ford' => [
            ['name' => 'F-150',    'model_year' => 2024],
            ['name' => 'Mustang',  'model_year' => 2024],
            ['name' => 'Explorer', 'model_year' => 2024],
            ['name' => 'Escape',   'model_year' => 2023],
            ['name' => 'Edge',     'model_year' => 2023],
            ['name' => 'Ranger',   'model_year' => 2024],
        ],
        'Chevrolet' => [
            ['name' => 'Malibu',    'model_year' => 2023],
            ['name' => 'Tahoe',     'model_year' => 2024],
            ['name' => 'Equinox',   'model_year' => 2024],
            ['name' => 'Camaro',    'model_year' => 2024],
            ['name' => 'Silverado', 'model_year' => 2024],
            ['name' => 'Suburban',  'model_year' => 2024],
        ],
        'Lexus' => [
            ['name' => 'ES', 'model_year' => 2024],
            ['name' => 'IS', 'model_year' => 2024],
            ['name' => 'LS', 'model_year' => 2023],
            ['name' => 'NX', 'model_year' => 2024],
            ['name' => 'RX', 'model_year' => 2024],
            ['name' => 'GX', 'model_year' => 2024],
            ['name' => 'LX', 'model_year' => 2024],
        ],
        'Mitsubishi' => [
            ['name' => 'Lancer',        'model_year' => 2022],
            ['name' => 'Outlander',     'model_year' => 2024],
            ['name' => 'Pajero',        'model_year' => 2023],
            ['name' => 'Eclipse Cross', 'model_year' => 2024],
            ['name' => 'L200',          'model_year' => 2024],
        ],
        'Mazda' => [
            ['name' => 'Mazda3', 'model_year' => 2024],
            ['name' => 'Mazda6', 'model_year' => 2023],
            ['name' => 'CX-5',   'model_year' => 2024],
            ['name' => 'CX-9',   'model_year' => 2023],
            ['name' => 'MX-5',   'model_year' => 2024],
        ],
        'Jeep' => [
            ['name' => 'Wrangler',       'model_year' => 2024],
            ['name' => 'Cherokee',       'model_year' => 2023],
            ['name' => 'Grand Cherokee', 'model_year' => 2024],
            ['name' => 'Compass',        'model_year' => 2024],
            ['name' => 'Renegade',       'model_year' => 2023],
        ],
        'Land Rover' => [
            ['name' => 'Defender',           'model_year' => 2024],
            ['name' => 'Discovery',          'model_year' => 2024],
            ['name' => 'Range Rover',        'model_year' => 2024],
            ['name' => 'Range Rover Sport',  'model_year' => 2024],
            ['name' => 'Freelander',         'model_year' => 2022],
        ],
        'Subaru' => [
            ['name' => 'Impreza',  'model_year' => 2024],
            ['name' => 'Forester', 'model_year' => 2024],
            ['name' => 'Outback',  'model_year' => 2024],
            ['name' => 'XV',       'model_year' => 2023],
            ['name' => 'BRZ',      'model_year' => 2024],
            ['name' => 'WRX',      'model_year' => 2024],
        ],
        'Porsche' => [
            ['name' => '911',       'model_year' => 2024],
            ['name' => 'Cayenne',   'model_year' => 2024],
            ['name' => 'Macan',     'model_year' => 2024],
            ['name' => 'Panamera',  'model_year' => 2024],
            ['name' => 'Taycan',    'model_year' => 2024],
        ],
        'Peugeot' => [
            ['name' => '208',  'model_year' => 2024],
            ['name' => '308',  'model_year' => 2024],
            ['name' => '508',  'model_year' => 2023],
            ['name' => '2008', 'model_year' => 2024],
            ['name' => '3008', 'model_year' => 2024],
            ['name' => '5008', 'model_year' => 2023],
        ],
        'Renault' => [
            ['name' => 'Clio',   'model_year' => 2024],
            ['name' => 'Megane', 'model_year' => 2023],
            ['name' => 'Koleos', 'model_year' => 2023],
            ['name' => 'Duster', 'model_year' => 2024],
            ['name' => 'Captur', 'model_year' => 2024],
        ],
    ];

    public function run(): void
    {
        foreach ($this->data as $brandName => $models) {
            $brand = Brand::firstOrCreate(['name' => $brandName]);

            foreach ($models as $model) {
                CarModel::firstOrCreate(
                    ['brand_id' => $brand->id, 'name' => $model['name']],
                    ['model_year' => $model['model_year']],
                );
            }
        }
    }
}
