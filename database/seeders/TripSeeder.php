<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\TripWeight;
use App\Models\TripFinancial;
use App\Models\TripStatus;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Personnel;
use App\Models\Product;
use App\Models\Site;
use App\Models\Tax;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TripSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch existing core data (created by earlier seeders)
        $entity = Entity::first();
        if (!$entity) return;

        // Note: Plant model uses 'id', but many tables use 'plant_id' as FK.
        $plant = Plant::where('entity_id', $entity->id)->first() ?? Plant::create([
            'name' => 'Main Quarry', 
            'entity_id' => $entity->id,
            'code' => 'MQ01',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $machine = Machine::where('plant_id', $plant->id)->first() ?? Machine::create([
            'registration' => 'KA-01-MT-1234',
            'model_name' => 'Tata Prima',
            'plant_id' => $plant->id,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $party = Patron::where('plant_id', $plant->id)->first() ?? Patron::create([
            'legal_name' => 'Global Construction Ltd',
            'email' => 'contact@global.com',
            'mobile' => '9876543210',
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vendor = Patron::where('plant_id', $plant->id)->skip(1)->first() ?? Patron::create([
            'legal_name' => 'Local Supplier Inc',
            'email' => 'sales@local.com',
            'mobile' => '9887766554',
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loadSite = Site::where('plant_id', $plant->id)->first() ?? Site::create([
            'name' => 'Quarry Site A',
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unloadSite = Site::where('plant_id', $plant->id)->skip(1)->first() ?? Site::create([
            'name' => 'Stockyard B',
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::where('plant_id', $plant->id)->first() ?? Product::create([
            'title' => '20mm Jelly',
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $driver = Personnel::where('plant_id', $plant->id)->first() ?? Personnel::create([
            'first_name' => 'Rahul',
            'last_name' => 'Kumar',
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tax = Tax::where('plant_id', $plant->id)->first() ?? Tax::create([
            'tax_name' => 'GST 5%',
            'tax_rate' => 5,
            'plant_id' => $plant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create sample trips
        for ($i = 0; $i < 5; $i++) {
            $tripType = $i % 2 == 0 ? 'outbound' : 'inbound';
            
            $tripId = DB::table('mm_trips')->insertGetId([
                'trip_type' => $tripType,
                'reference_id' => 'REF-' . strtoupper(Str::random(6)),
                'truck_id' => $machine->id,
                'truck_model' => $machine->model_name,
                'party_id' => $party->id,
                'vendor_id' => $vendor->id,
                'load_site_id' => $loadSite->id,
                'unload_site_id' => $unloadSite->id,
                'product_id' => $product->id,
                'driver_id' => $driver->id,
                'payment_mode' => 'credit',
                'plant_id' => $plant->id,
                'entity_id' => $entity->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Weights
            $emptyWeight = rand(10000, 15000);
            $loadedWeight = $emptyWeight + rand(20000, 30000);
            
            DB::table('mm_trip_weights')->insert([
                'trip_id' => $tripId,
                'empty_weight_load' => $emptyWeight,
                'loaded_weight_load' => $loadedWeight,
                'empty_weight_time' => now()->subHours(2),
                'loaded_weight_time' => now()->subHour(),
                'empty_weight_unload' => $emptyWeight + rand(-5, 5),
                'loaded_weight_unload' => $loadedWeight + rand(-100, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Financials
            $rate = rand(400, 600);
            $units = ($loadedWeight - $emptyWeight) / 1000;
            
            DB::table('mm_trip_financials')->insert([
                'trip_id' => $tripId,
                'product_units' => $units,
                'product_amount' => $rate,
                'product_tax_id' => $tax->id,
                'product_tax_amount' => ($units * $rate * 5) / 100,
                'transport_rate' => rand(200, 300),
                'transport_unit' => $units,
                'transport_tax_id' => $tax->id,
                'transport_tax_rate' => 5,
                'pass_amount' => rand(50, 150),
                'discount_amount' => 0,
                'transport_expenses' => rand(1000, 2000),
                'cost_of_product' => rand(2000, 4000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Status
            DB::table('mm_trip_statuses')->insert([
                'trip_id' => $tripId,
                'trip_status' => rand(0, 1),
                'is_closed' => rand(0, 1),
                'invoice_date' => now(),
                'invoice_number' => 'INV-' . strtoupper(Str::random(4)),
                'purchase_date' => now(),
                'purchase_invoice_number' => 'PUR-' . strtoupper(Str::random(4)),
                'transport_date' => now(),
                'transport_invoice_number' => 'TINV-' . strtoupper(Str::random(4)),
                'transport_km' => rand(50, 150),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
