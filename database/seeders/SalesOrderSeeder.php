<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\MixDesign;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Plant;
use App\Models\User;

class SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();
        if ($plants->isEmpty()) {
            $plants = collect([Plant::factory()->create(['name' => 'Main Plant'])]);
        }
        $plant = $plants->first();

        $patron = Patron::first() ?: Patron::factory()->create(['legal_name' => 'Acme Corporation']);
        $site = Site::first() ?: Site::factory()->create(['name' => 'Construction Site Alpha', 'patron_id' => $patron->id]);
        $user = User::first() ?: User::factory()->create(['username' => 'supervisor_john']);

        // Set roles & departments on user's personnel record if available
        $personnel = $user->personnel;
        if ($personnel) {
            $department = \App\Models\Department::first() ?: \App\Models\Department::create(['name' => 'Supervisor']);
            $personnel->update(['department_id' => $department->id]);
        }

        // Seed a mix design for every plant
        $mixDesignsByPlant = [];
        foreach ($plants as $p) {
            $md = MixDesign::where('plant_id', $p->id)->first();
            if (!$md) {
                $md = MixDesign::create([
                    'plant_id' => $p->id,
                    'partner_id' => $patron->id,
                    'design_name' => 'Ready Mix Concrete M25',
                    'design_type' => 'M25',
                    'design_code' => 'M25-STD',
                ]);
            }
            $mixDesignsByPlant[$p->id] = $md;
        }

        $mixDesign = $mixDesignsByPlant[$plant->id];

        // 1. Seed some unconverted Quotations (will show in the Source Quotation dropdown)
        for ($i = 1; $i <= 3; $i++) {
            $quote = Quotation::create([
                'plant_id' => $plant->id,
                'prefix' => 'QT',
                'reference' => 'QT-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'patron_id' => $patron->id,
                'site_id' => $site->id,
                'quote_date' => now()->subDays(rand(1, 10))->toDateString(),
                'validity_date' => now()->addDays(rand(10, 20)),
                'status' => 2, // Approved
                'is_salesorder' => 0,
                'amount_untaxed' => 5000 * $i,
                'amount_tax' => 900 * $i,
                'amount_total' => 5900 * $i,
            ]);

            QuotationItem::create([
                'quotation_id' => $quote->id,
                'mix_design_id' => $mixDesign->id,
                'quantity' => 10 * $i,
                'rate' => 500,
                'tax_amount' => 900 * $i,
                'untaxed_amount' => 5000 * $i,
                'amount_total' => 5900 * $i,
            ]);
        }

        // 2. Seed some converted Sales Orders
        for ($i = 4; $i <= 6; $i++) {
            $quote = Quotation::create([
                'plant_id' => $plant->id,
                'prefix' => 'QT',
                'reference' => 'QT-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'patron_id' => $patron->id,
                'site_id' => $site->id,
                'quote_date' => now()->subDays(rand(1, 10))->toDateString(),
                'validity_date' => now()->addDays(rand(10, 20)),
                'status' => 2, // Approved
                'is_salesorder' => 1,
                'amount_untaxed' => 5000 * $i,
                'amount_tax' => 900 * $i,
                'amount_total' => 5900 * $i,
            ]);

            QuotationItem::create([
                'quotation_id' => $quote->id,
                'mix_design_id' => $mixDesign->id,
                'quantity' => 10 * $i,
                'rate' => 500,
                'tax_amount' => 900 * $i,
                'untaxed_amount' => 5000 * $i,
                'amount_total' => 5900 * $i,
            ]);

            $roles = ['Sales', 'Supervisor', 'Marketing'];
            $departments = ['Commercial', 'Operations', 'Growth'];

            SalesOrder::create([
                'plant_id' => $plant->id,
                'quotation_id' => $quote->id,
                'patron_id' => $patron->id,
                'site_id' => $site->id,
                'order_date' => now()->subDays(rand(1, 5))->toDateString(),
                'status' => SalesOrder::STATUS_CONFIRMED,
                'converted_by_user_id' => $user->id,
                'converted_by_role' => $roles[$i - 4],
                'converted_by_department' => $departments[$i - 4],
            ]);
        }
    }
}
