<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrderTax;
use App\Models\Plant;
use App\Models\Tax;
use App\Models\Patron;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $plant = Plant::first();
        if (!$plant) return;

        $tax = Tax::where('tax_rate', '>', 0)->first();
        
        $patrons = Patron::take(2)->get();
        if ($patrons->count() < 2) return;

        Invoice::factory(5)->create([
            'plant_id' => $plant->id,
            'partner_id' => $patrons[1]->id,
            'invoice_type' => 'sales',
        ])->each(function ($invoice) use ($tax) {
            
            // Add items
            $items = InvoiceItem::factory(3)->make([
                'invoice_id' => $invoice->id,
            ]);
            
            foreach ($items as $item) {
                $item->compute($tax ? $tax->tax_rate : 0);
                $item->save();
            }
            
            // Generate Tax Split
            if ($tax && $tax->tax_rate > 0) {
                $invoice->refresh(); // get updated subtotal
                OrderTax::createIntraStateSplit($invoice, (float)$invoice->subtotal, (float)$tax->tax_rate);
            }
        });
        
        // Inter-state invoice
        Invoice::factory(2)->create([
            'plant_id' => $plant->id,
            'partner_id' => $patrons[0]->id,
            'invoice_type' => 'sales',
        ])->each(function ($invoice) use ($tax) {
            $item = new InvoiceItem([
                'invoice_id' => $invoice->id,
                'item_name' => 'Inter-state Transport',
                'hsn_code' => '996511',
                'quantity' => 1,
                'price_unit' => 50000,
                'discount_type' => '%',
                'discount' => 0,
            ]);
            $item->compute($tax ? $tax->tax_rate : 0);
            $item->save();
            
            if ($tax && $tax->tax_rate > 0) {
                $invoice->refresh();
                OrderTax::createInterStateSplit($invoice, (float)$invoice->subtotal, (float)$tax->tax_rate);
            }
        });
    }
}
