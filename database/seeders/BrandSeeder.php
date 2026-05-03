<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    private array $data = [
        'Toyota'        => ['Camry', 'Corolla', 'RAV4', 'Land Cruiser', 'Hilux', 'Prius', 'Yaris', 'Fortuner'],
        'Honda'         => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Pilot', 'Jazz'],
        'Nissan'        => ['Altima', 'Maxima', 'Patrol', 'X-Trail', 'Navara', 'Sunny', 'Tiida'],
        'Hyundai'       => ['Elantra', 'Tucson', 'Santa Fe', 'Sonata', 'i10', 'i20', 'Creta'],
        'Kia'           => ['Cerato', 'Sportage', 'Sorento', 'Picanto', 'Stinger', 'Carnival'],
        'BMW'           => ['3 Series', '5 Series', '7 Series', 'X3', 'X5', 'X6', 'M3', 'M5'],
        'Mercedes-Benz' => ['C-Class', 'E-Class', 'S-Class', 'GLC', 'GLE', 'GLS', 'A-Class'],
        'Audi'          => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7', 'RS4'],
        'Volkswagen'    => ['Golf', 'Passat', 'Tiguan', 'Touareg', 'Polo', 'Arteon'],
        'Ford'          => ['F-150', 'Mustang', 'Explorer', 'Escape', 'Edge', 'Ranger'],
        'Chevrolet'     => ['Malibu', 'Tahoe', 'Equinox', 'Camaro', 'Silverado', 'Suburban'],
        'Lexus'         => ['ES', 'IS', 'LS', 'NX', 'RX', 'GX', 'LX'],
        'Mitsubishi'    => ['Lancer', 'Outlander', 'Pajero', 'Eclipse Cross', 'L200'],
        'Mazda'         => ['Mazda3', 'Mazda6', 'CX-5', 'CX-9', 'MX-5'],
        'Jeep'          => ['Wrangler', 'Cherokee', 'Grand Cherokee', 'Compass', 'Renegade'],
        'Land Rover'    => ['Defender', 'Discovery', 'Range Rover', 'Range Rover Sport', 'Freelander'],
        'Subaru'        => ['Impreza', 'Forester', 'Outback', 'XV', 'BRZ', 'WRX'],
        'Porsche'       => ['911', 'Cayenne', 'Macan', 'Panamera', 'Taycan'],
        'Peugeot'       => ['208', '308', '508', '2008', '3008', '5008'],
        'Renault'       => ['Clio', 'Megane', 'Koleos', 'Duster', 'Captur'],
    ];

    public function run(): void
    {
        foreach ($this->data as $brandName => $models) {
            $brand = Brand::firstOrCreate(['name' => $brandName]);

            foreach ($models as $modelName) {
                CarModel::firstOrCreate([
                    'brand_id' => $brand->id,
                    'name'     => $modelName,
                ]);
            }
        }
    }
}
