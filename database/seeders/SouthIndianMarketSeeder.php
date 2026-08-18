<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Site;
use App\Models\User;
use App\Models\MixDesign;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\CustomerPO;
use App\Models\SalesOrder;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;

class SouthIndianMarketSeeder extends Seeder
{
    public function run(): void
    {
        $plant = Plant::where('id', 2)->first();
        // $plant = Plant::first();
        // if (!$plant) {
        //     $plant = Plant::factory()->create(['name' => 'Chennai RMC Plant']);
        // }
        
        $user = User::first();
        $uom = ProductUnit::firstOrCreate(
            ['unit_code' => 'M3'],
            ['unit_name' => 'Cubic Meter', 'unit_type' => 'Measure']
        );
        $uomKg = ProductUnit::firstOrCreate(
            ['unit_code' => 'KG'],
            ['unit_name' => 'Kilogram', 'unit_type' => 'Measure']
        );

        // 1. Seed Patrons (South Indian Market)
        $customers = [
            'Sobha Developers',
            'Prestige Estates',
            'Brigade Group',
            'Puravankara',
            'L&T Constructions (Chennai)',
            'Casagrand Builder Private Limited'
        ];

        $vendors = [
            'Ramco Cements',
            'Bharathi Cements',
            'UltraTech Cement (South)',
            'Dalmia Bharat',
            'Chettinad Cement'
        ];

        $transporters = [
            'Sri Ramadas Motor Transport',
            'KPN Travels',
            'Navata Road Transport'
        ];

        $customerModels = [];
        foreach ($customers as $c) {
            $customerModels[] = Patron::firstOrCreate(
                ['legal_name' => $c],
                [
                    'plant_id' => $plant->id,
                    'patron_type' => ['Customer'],
                    'operational_status' => 1,
                    'status' => 1,
                    'displayed' => 1,
                    'is_system' => 0
                ]
            );
        }

        $vendorModels = [];
        foreach ($vendors as $v) {
            $vendorModels[] = Patron::firstOrCreate(
                ['legal_name' => $v],
                [
                    'plant_id' => $plant->id,
                    'patron_type' => ['Vendor'],
                    'operational_status' => 1,
                    'status' => 1,
                    'displayed' => 1,
                    'is_system' => 0
                ]
            );
        }

        $transporterModels = [];
        foreach ($transporters as $t) {
            $transporterModels[] = Patron::firstOrCreate(
                ['legal_name' => $t],
                [
                    'plant_id' => $plant->id,
                    'patron_type' => ['Transporter'],
                    'operational_status' => 1,
                    'status' => 1,
                    'displayed' => 1,
                    'is_system' => 0
                ]
            );
        }

        // 2. Setup Base Data for Orders
        $site = Site::firstOrCreate(
            ['name' => 'OMR IT Park Construction'],
            ['plant_id' => $plant->id, 'site_address_1' => 'OMR, Chennai', 'status' => 'Active', 'is_active' => true, 'type' => 'unloading']
        );
        
        $cement = Product::firstOrCreate(
            ['title' => 'OPC 53 Grade Cement'],
            ['plant_id' => $plant->id, 'type' => 'Raw Material', 'uom_id' => $uomKg->id, 'status' => 1]
        );

        $concreteGrade = \App\Models\ConcreteGrade::firstOrCreate(
            ['name' => 'M40'],
            ['plant_id' => $plant->id, 'status' => 1]
        );

        $mixDesign = MixDesign::firstOrCreate(
            ['design_name' => 'M40 High Strength (South)'],
            [
                'plant_id' => $plant->id, 
                'design_type' => 'M40', 
                'design_code' => 'M40-HS-01',
                'concrete_grade_id' => $concreteGrade->id,
                'unit_id' => $uom->id,
                'partner_id' => $customerModels[0]->id
            ]
        );

        $truck = Machine::firstOrCreate(
            ['registration' => 'TN-01-AB-1234'],
            ['plant_id' => $plant->id, 'vehicle_type' => 'Transit Mixer', 'is_active' => 1]
        );

        // 3. Purchase Orders (Raw Materials from Vendors)
        foreach ($vendorModels as $index => $vendor) {
            if ($index > 2) break; // Create 3 POs
            
            $poData = PurchaseOrder::generateNextRefId($plant->id, now()->toDateString());
            $po = PurchaseOrder::create([
                'plant_id' => $plant->id,
                'vendor_id' => $vendor->id,
                'po_number' => $poData['ref_no'],
                'ref_no' => substr($poData['ref_no'], -4),
                'date_order' => now()->subDays(rand(1, 15))->toDateString(),
                'state' => 'purchase',
                'amount_untaxed' => 50000,
                'amount_tax' => 9000,
                'amount_total' => 59000,
                'created_by' => $user->id ?? 1
            ]);

            PurchaseOrderItem::create([
                'order_id' => $po->id,
                'plant_id' => $plant->id,
                'product_id' => $cement->id,
                'description' => 'OPC 53 Grade Cement',
                'product_quantity' => 10000, // 10 Tons
                'product_uom' => $uomKg->id,
                'unit_price' => 5.00,
                'price_subtotal' => 50000,
                'price_tax' => 9000,
                'price_total' => 59000,
            ]);
        }

        // Seed Personnel (Sales Executive)
        $department = \App\Models\Department::firstOrCreate(['name' => 'Sales', 'plant_id' => $plant->id]);
        $designation = \App\Models\Designation::firstOrCreate(['name' => 'Sales Executive', 'code' => 'SE', 'plant_id' => $plant->id]);
        
        $salesExecutive = \App\Models\Personnel::firstOrCreate(
            ['employee_code' => 'EMP-SE-001'],
            [
                'plant_id' => $plant->id,
                'first_name' => 'Ramesh',
                'last_name' => 'Kumar',
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'status' => 'Active'
            ]
        );

        // 4. Customer POs (Orders from Customers for Concrete)
        $customerPo = CustomerPO::create([
            'plant_id' => $plant->id,
            'patron_id' => $customerModels[0]->id, // Sobha Developers
            'site_id' => $site->id,
            'sales_executive_id' => $salesExecutive->id,
            'order_date' => now()->subDays(5)->toDateString(),
            'status' => CustomerPO::STATUS_CONFIRMED,
            'concrete_pump' => 1,
            'created_by' => $user->id ?? 1
        ]);

        // 5. Sales Orders (Work Orders linked to Customer PO)
        $soData = SalesOrder::generateOrderNo($plant->id);
        $salesOrder = SalesOrder::create([
            'prefix' => $soData['prefix'],
            'order_no' => $soData['next_number'],
            'plant_id' => $plant->id,
            'customer_id' => $customerModels[0]->id,
            'site_id' => $site->id,
            'mix_design_id' => $mixDesign->id,
            'total_qty' => 500.000,
            'produced_qty' => 50.000,
            'scheduled_start' => now()->subDays(1),
            'scheduled_end' => now()->addDays(5),
            'status' => SalesOrder::STATUS_IN_PROGRESS,
            'customer_po_id' => $customerPo->id,
            'concrete_pump' => 1,
            'created_by' => $user->id ?? 1
        ]);

        // 6. Dispatches
        for ($i = 1; $i <= 3; $i++) {
            Dispatch::create([
                'plant_id' => $plant->id,
                'sales_order_id' => $salesOrder->id,
                'mixdesign_id' => $mixDesign->id,
                'customer_id' => $customerModels[0]->id,
                'sales_executive_id' => $salesExecutive->id,
                'truck_id' => $truck->id,
                'transport_id' => $transporterModels[0]->id, // Sri Ramadas
                'delivered_qty' => 7.000, // 7 M3
                'dispatch_no' => 'DC-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'dispatch_time' => now()->subHours(rand(1, 24)),
                'load_rate' => 4500,
                'load_untax_amount' => 4500 * 7,
                'load_tax_amount' => (4500 * 7) * 0.18,
                'load_total_amount' => (4500 * 7) * 1.18,
                'dispatch_status' => 'Delivered',
                'created_by' => $user->id ?? 1
            ]);
        }
    }
}
