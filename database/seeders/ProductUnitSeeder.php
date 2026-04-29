<?php

namespace Database\Seeders;

use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ProductUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['unit_name' => 'BAGS', 'unit_type' => 'Measure', 'unit_code' => 'BAG'],
            ['unit_name' => 'BALE', 'unit_type' => 'Measure', 'unit_code' => 'BAL'],
            ['unit_name' => 'BUNDLES', 'unit_type' => 'Measure', 'unit_code' => 'BDL'],
            ['unit_name' => 'BUCKLES', 'unit_type' => 'Measure', 'unit_code' => 'BKL'],
            ['unit_name' => 'BILLIONS OF UNITS', 'unit_type' => 'Measure', 'unit_code' => 'BOU'],
            ['unit_name' => 'BOX', 'unit_type' => 'Measure', 'unit_code' => 'BOX'],
            ['unit_name' => 'BOTTLES', 'unit_type' => 'Measure', 'unit_code' => 'BTL'],
            ['unit_name' => 'BUNCHES', 'unit_type' => 'Measure', 'unit_code' => 'BUN'],
            ['unit_name' => 'CANS', 'unit_type' => 'Measure', 'unit_code' => 'CAN'],
            ['unit_name' => 'CUBIC METER', 'unit_type' => 'Volume', 'unit_code' => 'CBM'],
            ['unit_name' => 'CUBIC CENTIMETER', 'unit_type' => 'Volume', 'unit_code' => 'CCM'],
            ['unit_name' => 'CENTIMETER', 'unit_type' => 'Length', 'unit_code' => 'CMS'],
            ['unit_name' => 'CARTONS', 'unit_type' => 'Measure', 'unit_code' => 'CTN'],
            ['unit_name' => 'DOZEN', 'unit_type' => 'Measure', 'unit_code' => 'DOZ'],
            ['unit_name' => 'DRUM', 'unit_type' => 'Measure', 'unit_code' => 'DRM'],
            ['unit_name' => 'GREAT GROSS', 'unit_type' => 'Measure', 'unit_code' => 'GGR'],
            ['unit_name' => 'GRAMS', 'unit_type' => 'Weight', 'unit_code' => 'GMS'],
            ['unit_name' => 'GROSS', 'unit_type' => 'Measure', 'unit_code' => 'GRS'],
            ['unit_name' => 'GROSS YARDS', 'unit_type' => 'Length', 'unit_code' => 'GYD'],
            ['unit_name' => 'KILOGRAMS', 'unit_type' => 'Weight', 'unit_code' => 'KGS'],
            ['unit_name' => 'KILOLITER', 'unit_type' => 'Volume', 'unit_code' => 'KLR'],
            ['unit_name' => 'KILOMETRE', 'unit_type' => 'Length', 'unit_code' => 'KME'],
            ['unit_name' => 'MILLILITRE', 'unit_type' => 'Volume', 'unit_code' => 'MLT'],
            ['unit_name' => 'METERS', 'unit_type' => 'Length', 'unit_code' => 'MTR'],
            ['unit_name' => 'METRIC TONS', 'unit_type' => 'Weight', 'unit_code' => 'MTS'],
            ['unit_name' => 'NUMBERS', 'unit_type' => 'Measure', 'unit_code' => 'NOS'],
            ['unit_name' => 'PACKS', 'unit_type' => 'Measure', 'unit_code' => 'PAC'],
            ['unit_name' => 'PIECES', 'unit_type' => 'Measure', 'unit_code' => 'PCS'],
            ['unit_name' => 'PAIRS', 'unit_type' => 'Measure', 'unit_code' => 'PRS'],
            ['unit_name' => 'QUINTAL', 'unit_type' => 'Weight', 'unit_code' => 'QTL'],
            ['unit_name' => 'ROLLS', 'unit_type' => 'Measure', 'unit_code' => 'ROL'],
            ['unit_name' => 'SETS', 'unit_type' => 'Measure', 'unit_code' => 'SET'],
            ['unit_name' => 'SQUARE FEET', 'unit_type' => 'Area', 'unit_code' => 'SQF'],
            ['unit_name' => 'SQUARE METERS', 'unit_type' => 'Area', 'unit_code' => 'SQM'],
            ['unit_name' => 'SQUARE YARDS', 'unit_type' => 'Area', 'unit_code' => 'SQY'],
            ['unit_name' => 'TABLETS', 'unit_type' => 'Measure', 'unit_code' => 'TBS'],
            ['unit_name' => 'TEN GROSS', 'unit_type' => 'Measure', 'unit_code' => 'TGM'],
            ['unit_name' => 'THOUSANDS', 'unit_type' => 'Measure', 'unit_code' => 'THD'],
            ['unit_name' => 'TONNES', 'unit_type' => 'Weight', 'unit_code' => 'TON'],
            ['unit_name' => 'TUBES', 'unit_type' => 'Measure', 'unit_code' => 'TUB'],
            ['unit_name' => 'US GALLONS', 'unit_type' => 'Volume', 'unit_code' => 'UGS'],
            ['unit_name' => 'UNITS', 'unit_type' => 'Measure', 'unit_code' => 'UNT'],
            ['unit_name' => 'YARDS', 'unit_type' => 'Length', 'unit_code' => 'YDS'],
            ['unit_name' => 'OTHERS', 'unit_type' => 'Other', 'unit_code' => 'OTH'],
        ];

        foreach ($units as $unit) {
            ProductUnit::updateOrCreate(
                [
                    'unit_name' => $unit['unit_name'],
                    'unit_type' => $unit['unit_type'],
                ],
                [
                    'unit_code' => $unit['unit_code'],
                ]
            );
        }
    }
}
