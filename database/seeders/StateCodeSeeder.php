<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StateCode;
use App\Models\Country;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StateCodeSeeder extends Seeder
{
    public function run(): void
    {
        // Prevent timeout and memory exhaustion
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // Ensure India exists in the countries table first
        $india = Country::where('country_code', 'IN')->first();
        if (!$india) {
            $india = Country::create([
                'country_code' => 'IN',
                'country_name' => 'India',
                'is_active' => 1
            ]);
        }

        $states = [
            ['state_code' => '01', 'state_name' => 'Jammu & Kashmir'],
            ['state_code' => '02', 'state_name' => 'Himachal Pradesh'],
            ['state_code' => '03', 'state_name' => 'Punjab'],
            ['state_code' => '04', 'state_name' => 'Chandigarh'],
            ['state_code' => '05', 'state_name' => 'Uttarakhand'],
            ['state_code' => '06', 'state_name' => 'Haryana'],
            ['state_code' => '07', 'state_name' => 'Delhi'],
            ['state_code' => '08', 'state_name' => 'Rajasthan'],
            ['state_code' => '09', 'state_name' => 'Uttar Pradesh'],
            ['state_code' => '10', 'state_name' => 'Bihar'],
            ['state_code' => '11', 'state_name' => 'Sikkim'],
            ['state_code' => '12', 'state_name' => 'Arunachal Pradesh'],
            ['state_code' => '13', 'state_name' => 'Nagaland'],
            ['state_code' => '14', 'state_name' => 'Manipur'],
            ['state_code' => '15', 'state_name' => 'Mizoram'],
            ['state_code' => '16', 'state_name' => 'Tripura'],
            ['state_code' => '17', 'state_name' => 'Meghalaya'],
            ['state_code' => '18', 'state_name' => 'Assam'],
            ['state_code' => '19', 'state_name' => 'West Bengal'],
            ['state_code' => '20', 'state_name' => 'Jharkhand'],
            ['state_code' => '21', 'state_name' => 'Odisha'],
            ['state_code' => '22', 'state_name' => 'Chhattisgarh'],
            ['state_code' => '23', 'state_name' => 'Madhya Pradesh'],
            ['state_code' => '24', 'state_name' => 'Gujarat'],
            ['state_code' => '26', 'state_name' => 'Dadra & Nagar Haveli and Daman & Diu'],
            ['state_code' => '27', 'state_name' => 'Maharashtra'],
            ['state_code' => '29', 'state_name' => 'Karnataka'],
            ['state_code' => '30', 'state_name' => 'Goa'],
            ['state_code' => '31', 'state_name' => 'Lakshadweep'],
            ['state_code' => '32', 'state_name' => 'Kerala'],
            ['state_code' => '34', 'state_name' => 'Puducherry'],
            ['state_code' => '35', 'state_name' => 'Andaman & Nicobar Islands'],
            ['state_code' => '36', 'state_name' => 'Telangana'],
            ['state_code' => '37', 'state_name' => 'Andhra Pradesh'],
            ['state_code' => '38', 'state_name' => 'Ladakh']
        ];

        // Seed other states
        foreach ($states as $state) {
            StateCode::updateOrCreate(
                [
                    'country_id' => $india->id,
                    'state_code' => $state['state_code']
                ],
                [
                    'state_name' => $state['state_name'],
                    'zipcode' => null,
                    'area' => null,
                    'district' => null
                ]
            );
        }

        // Parse and Seed Tamil Nadu from Excel
        $filePath = 'C:/Users/mahar/Downloads/state tamil nadu.xlsx';

        if (!file_exists($filePath)) {
            $this->command->error("Excel file not found at: {$filePath}. Seeding fallback single Tamil Nadu record.");
            StateCode::updateOrCreate(
                ['country_id' => $india->id, 'state_code' => '33'],
                ['state_name' => 'Tamil Nadu']
            );
            return;
        }

        $this->command->info("Loading Excel file for Tamil Nadu: {$filePath}...");
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            $this->command->error("Failed to read Excel file: " . $e->getMessage());
            return;
        }

        $totalRows = count($rows);
        $this->command->info("Found {$totalRows} Tamil Nadu rows. Cleansing previous Tamil Nadu records...");

        // Clean out existing Tamil Nadu state records to avoid duplicates
        StateCode::where('state_code', '33')->delete();

        $insertData = [];
        $chunkSize = 1000;
        $count = 0;

        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 1 || empty($row['B'])) {
                continue;
            }

            $area = trim($row['A'] ?? '');
            $zipcode = trim($row['B'] ?? '');
            $district = trim($row['C'] ?? '');
            
            $districtFormatted = ucwords(strtolower($district));

            $insertData[] = [
                'country_id' => $india->id,
                'state_code' => '33',
                'state_name' => 'Tamil Nadu',
                'zipcode' => $zipcode,
                'area' => $area,
                'district' => $districtFormatted,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $count++;

            if (count($insertData) >= $chunkSize) {
                DB::table('mm_state_codes')->insert($insertData);
                $insertData = [];
                $this->command->info("Seeded {$count} / {$totalRows} Tamil Nadu locations...");
            }
        }

        if (!empty($insertData)) {
            DB::table('mm_state_codes')->insert($insertData);
            $this->command->info("Seeded {$count} / {$totalRows} Tamil Nadu locations...");
        }

        $this->command->info("Successfully seeded {$count} Tamil Nadu locations directly into mm_state_codes!");
    }
}