<?php
/**
 * PrintDataFormatter — Normalizes data from any Eloquent model
 * into a common $data array that all blade templates consume.
 *
 * Usage:
 *   $data = PrintDataFormatter::fromPurchaseOrder($order);
 *   $data = PrintDataFormatter::fromQuotation($quotation);
 *   $data = PrintDataFormatter::fromInvoice($invoice);
 */

namespace App\Services;

use App\Models\PrintTemplate;
use App\Models\PrintTemplateSetting;

class PrintDataFormatter
{
    // ─────────────────────────────────────────────────────
    //  COMMON KEYS — every template receives these exact keys
    // ─────────────────────────────────────────────────────
    /**
     * Returns the base schema (empty / default values).
     * Override per module.
     */
    public static function base(): array
    {
        return [
            // ── Document Meta ──
            'doc_title'    => 'DOCUMENT',       // e.g. PURCHASE ORDER / TAX INVOICE
            'doc_no'       => '',               // ref_no / invoice_no
            'doc_date'     => '',               // formatted date_order
            'due_date'     => '',
            'delivery_date'=> '',
            'state'        => 'DRAFT',          // document state/status
            'terms'        => 'Net 30',

            // ── Company (issuer = plant) ──
            'company' => [
                'name'    => '',
                'address' => '',
                'city'    => '',
                'state'   => '',
                'pin'     => '',
                'gstin'   => '',
                'phone'   => '',
                'email'   => '',
            ],

            // ── Bill To (Customer / Vendor) ──
            'bill_to' => [
                'name'    => '',
                'address' => '',
                'city'    => '',
                'state'   => '',
                'pin'     => '',
                'gstin'   => '',
                'phone'   => '',
            ],

            // ── Ship To (Delivery address) ──
            'ship_to' => [
                'name'    => '',
                'address' => '',
                'city'    => '',
                'state'   => '',
                'pin'     => '',
            ],

            // ── Line Items ──
            'items' => [],

            // ── Totals ──
            'totals' => [
                'sub_total'   => 0,
                'discount'    => 0,
                'tax_lines'   => [],
                'shipping'    => 0,
                'grand_total' => 0,
            ],

            // ── Extras / Module-specific ──
            'meta' => [
                'po_number'       => '',
                'project_name'    => '',
                'currency_code'   => 'INR',
                'currency_symbol' => '₹',
                'notes'           => '',
                'terms_text'      => '',
                'total_words'     => '',
                'site_incharge'   => '',
                'contact_no'      => '',
            ],
        ];
    }

    /**
     * Returns a full dummy data set for previewing.
     */
    public static function dummy(string $category = 'invoice'): array
    {
        $data = self::base();
        $data['doc_title'] = strtoupper($category) . ' DOCUMENT';
        $data['doc_no']    = 'REF-2026-001';
        $data['doc_date']  = now()->format('d/m/Y');
        $data['due_date']  = now()->addDays(15)->format('d/m/Y');
        $data['delivery_date'] = now()->addDays(5)->format('d/m/Y');

        $data['company'] = [
            'name'    => 'ModoMines Tech Solutions',
            'address' => '123 Cloud Avenue, Tech Park',
            'city'    => 'Chennai',
            'state'   => 'Tamil Nadu',
            'pin'     => '600001',
            'gstin'   => '33AAAAA0000A1Z5',
            'phone'   => '+91 98765 43210',
            'email'   => 'support@modomines.com',
        ];

        $data['bill_to'] = [
            'name'    => 'Alpha Prime Industries',
            'address' => '45 Industrial Estate, Phase II',
            'city'    => 'Coimbatore',
            'state'   => 'Tamil Nadu',
            'pin'     => '641001',
            'gstin'   => '33BBBBB1111B1Z2',
            'phone'   => '+91 422 2345678',
        ];

        $data['ship_to'] = [
            'name'    => 'Alpha Prime - Site A',
            'address' => 'Plot 88, Near New Bypass',
            'city'    => 'Salem',
            'state'   => 'Tamil Nadu',
            'pin'     => '636001',
        ];

        $data['items'] = [
            [
                'no'           => 1,
                'name'         => 'High Grade Concrete Mix (M40)',
                'description'  => 'Standard grade for heavy structural works',
                'hsn'          => '382450',
                'qty'          => 45.00,
                'received_qty' => 45.00,
                'unit'         => 'm³',
                'unit_price'   => 4500.00,
                'tax_name'     => 'GST 12%',
                'tax_rate'     => 12,
                'tax_group'    => 'GST',
                'tax_amount'   => 24300.00,
                'total'        => 226800.00,
            ],
            [
                'no'           => 2,
                'name'         => 'Reinforcement Steel (12mm)',
                'description'  => 'TMT Bars - FE500D Grade',
                'hsn'          => '721420',
                'qty'          => 2.50,
                'received_qty' => 0.00,
                'unit'         => 'MT',
                'unit_price'   => 62000.00,
                'tax_name'     => 'GST 18%',
                'tax_rate'     => 18,
                'tax_group'    => 'GST',
                'tax_amount'   => 27900.00,
                'total'        => 182900.00,
            ]
        ];

        $data['totals'] = [
            'sub_total'   => 357500.00,
            'discount'    => 5000.00,
            'tax_lines'   => [
                ['label' => 'CGST', 'amount' => 26100.00],
                ['label' => 'SGST', 'amount' => 26100.00],
            ],
            'shipping'    => 1200.00,
            'grand_total' => 405900.00,
        ];

        $data['meta']['total_words']    = 'Indian Rupee Four Lakh Five Thousand Nine Hundred Only';
        $data['meta']['project_name']   = 'Grand Mall Construction - Phase 1';
        $data['meta']['po_number']     = 'PO-8877';
        $data['meta']['terms_text']    = "1. Payment within 15 days of delivery.\n2. Goods once sold will not be taken back.\n3. Subject to Chennai Jurisdiction.";

        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  PURCHASE ORDER
    // ─────────────────────────────────────────────────────
    public static function fromPurchaseOrder($order): array
    {
        $order->loadMissing(['items.product', 'items.uom', 'items.tax', 'vendor', 'plant', 'plant.entity', 'currency']);

        $data = self::base();

        // Document meta
        $data['doc_title']     = 'PURCHASE ORDER';
        $data['doc_no']        = $order->ref_no;
        $data['doc_date']      = $order->date_order?->format('d/m/Y') ?? 'N/A';
        $data['due_date']      = $order->due_date?->format('d/m/Y') ?? 'N/A';
        $data['delivery_date'] = $order->date_planned?->format('d/m/Y') ?? 'N/A';
        $data['state']         = strtoupper($order->state ?? 'DRAFT');

        // Company (plant as issuer)
        $data['company'] = [
            'name'    => $order->plant->entity->entity_name ?? $order->plant->name,
            'address' => $order->plant->address,
            'city'    => $order->plant->city,
            'state'   => $order->plant->state,
            'pin'     => $order->plant->pincode,
            'gstin'   => $order->plant->gstin ?? '',
            'phone'   => $order->plant->phone ?? '',
            'email'   => $order->plant->email ?? '',
        ];

        // Bill To (Vendor)
        $data['bill_to'] = [
            'name'    => $order->vendor->legal_name,
            'address' => $order->vendor->address_line1,
            'city'    => $order->vendor->city ?? '',
            'state'   => $order->vendor->state ?? '',
            'pin'     => $order->vendor->pincode ?? '',
            'gstin'   => $order->vendor->gstin ?? '',
            'phone'   => $order->vendor->phone ?? '',
        ];

        // Ship To (Delivery = plant)
        $data['ship_to'] = [
            'name'    => $order->plant->entity->entity_name ?? $order->plant->name,
            'address' => $order->plant->address,
            'city'    => $order->plant->city,
            'state'   => $order->plant->state,
            'pin'     => $order->plant->pincode,
        ];

        // Items
        $data['items'] = $order->items->map(function ($item, $idx) {
            return [
                'no'           => $idx + 1,
                'name'         => $item->product->title,
                'description'  => $item->description ?? '',
                'hsn'          => $item->product->hsn_code ?? '-',
                'qty'          => (float)$item->product_quantity,
                'received_qty' => (float)($item->received_quantity ?? 0),
                'unit'         => $item->uom->unit_code ?? '',
                'unit_price'   => (float)$item->unit_price,
                'tax_name'     => $item->tax?->tax_name ?? '-',
                'tax_rate'     => (float)($item->tax?->tax_rate ?? 0),
                'tax_group'    => $item->tax?->tax_group ?? '',
                'tax_amount'   => (float)($item->price_tax ?? 0),
                'total'        => (float)$item->price_total,
            ];
        })->toArray();

        // Tax lines summary
        $taxLines = [];
        foreach ($order->items as $item) {
            if (!$item->tax) continue;
            $g = $item->tax->tax_group;
            if ($g === 'GST') {
                $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($item->price_tax / 2);
                $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($item->price_tax / 2);
            } else {
                $taxLines[$g] = ($taxLines[$g] ?? 0) + $item->price_tax;
            }
        }

        $data['totals'] = [
            'sub_total'   => (float)$order->amount_untaxed,
            'discount'    => (float)($order->discount_amount ?? 0),
            'tax_lines'   => collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray(),
            'shipping'    => (float)($order->shipping_charges ?? 0),
            'grand_total' => (float)$order->amount_total,
        ];

        // Meta
        $data['meta'] = [
            'po_number'       => $order->po_number ?? $order->ref_no,
            'project_name'    => $order->plant->name,
            'currency_code'   => $order->currency->currency_code ?? 'INR',
            'currency_symbol' => $order->currency->currency_symbol ?? '₹',
            'notes'           => $order->notes ?? '',
            'terms_text'      => $order->terms_conditions ?? '',
            'total_words'     => self::numberToWords($order->amount_total, $order->currency->currency_code ?? 'INR'),
            'site_incharge'   => $order->plant->site_incharge ?? '',
            'contact_no'      => $order->plant->contact_no ?? '',
            'receipt_status'  => (int)$order->receipt_status,
        ];

        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  INVOICE
    // ─────────────────────────────────────────────────────
    public static function fromInvoice($invoice): array
    {
        $invoice->loadMissing(['plant', 'plant.entity', 'partner', 'items.tax', 'items.uom', 'orderTaxes']);

        $data = self::base();
        $data['settings'] = self::getCustomSettings($invoice->plant_id, 'invoices');

        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'TAX INVOICE';
        $data['doc_no']    = $invoice->invoice_number ?? $invoice->id;
        $data['doc_date']  = $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $invoice->due_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($invoice->status ?? 'DRAFT');

        // Company
        $data['company'] = [
            'name'    => $invoice->plant->entity->entity_name ?? $invoice->plant->name ?? 'Company',
            'address' => $invoice->plant->address ?? '',
            'city'    => $invoice->plant->city ?? '',
            'state'   => $invoice->plant->state ?? '',
            'pin'     => $invoice->plant->pincode ?? '',
            'gstin'   => $invoice->plant->gstin ?? '',
            'phone'   => $invoice->plant->phone ?? '',
            'email'   => $invoice->plant->email ?? '',
        ];

        // Bill To
        $data['bill_to'] = [
            'name'    => $invoice->partner->legal_name ?? $invoice->partner->name ?? 'N/A',
            'address' => $invoice->partner->address_line1 ?? '',
            'city'    => $invoice->partner->city ?? '',
            'state'   => $invoice->partner->state ?? '',
            'pin'     => $invoice->partner->pincode ?? '',
            'gstin'   => $invoice->partner->gstin ?? '',
            'phone'   => $invoice->partner->phone ?? '',
        ];

        // Ship To
        $data['ship_to'] = $data['bill_to'];

        // Items
        $data['items'] = $invoice->items->map(function ($item, $idx) {
            return [
                'no'           => $idx + 1,
                'name'         => $item->item_name,
                'description'  => $item->hsn_code ? 'HSN: ' . $item->hsn_code : '',
                'hsn'          => $item->hsn_code ?? '-',
                'qty'          => (float)$item->quantity,
                'unit'         => $item->uom->unit_code ?? 'm³',
                'unit_price'   => (float)$item->price_unit,
                'tax_name'     => $item->tax?->tax_name ?? '-',
                'tax_rate'     => (float)($item->tax?->tax_rate ?? 0),
                'tax_group'    => $item->tax?->tax_group ?? '',
                'tax_amount'   => (float)($item->line_tax_amount ?? 0),
                'total'        => (float)($item->line_total ?? ($item->quantity * $item->price_unit)),
            ];
        })->toArray();

        // Totals
        $taxLines = $invoice->orderTaxes->map(function($ot) {
            return ['label' => $ot->name, 'amount' => (float)$ot->amount];
        })->toArray();

        $data['totals'] = [
            'sub_total'   => (float)$invoice->subtotal,
            'discount'    => (float)$invoice->discount_total,
            'tax_lines'   => $taxLines,
            'shipping'    => (float)$invoice->shipping_charges,
            'adjustment'  => (float)$invoice->adjustment,
            'grand_total' => (float)$invoice->total_amount,
        ];

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => '',
            'terms_text'      => "1. Goods once sold will not be taken back.\n2. Interest @ 18% will be charged if not paid within due date.\n3. All disputes are subject to local jurisdiction.",
            'total_words'     => self::numberToWords($invoice->total_amount, 'INR'),
            'po_number'       => $invoice->ref_id ?? '',
            'project_name'    => $invoice->ref_title ?? '',
        ];

        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  QUOTATION
    // ─────────────────────────────────────────────────────
    public static function fromQuotation($quotation): array
    {
        $quotation->loadMissing(['items.mixDesign', 'items.mixDesign.unit', 'patron', 'plant', 'plant.entity', 'tax']);

        $data = self::base();
        $data['doc_title'] = 'QUOTATION';
        $data['doc_no']    = $quotation->reference ?? $quotation->id;
        $data['doc_date']  = $quotation->quote_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $quotation->validity_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($quotation->status_text ?? 'DRAFT');

        // Company
        $data['company'] = [
            'name'    => $quotation->plant->entity->entity_name ?? $quotation->plant->name,
            'address' => $quotation->plant->address,
            'city'    => $quotation->plant->city,
            'state'   => $quotation->plant->state,
            'pin'     => $quotation->plant->pincode,
            'gstin'   => $quotation->plant->gstin ?? '',
            'phone'   => $quotation->plant->phone ?? '',
            'email'   => $quotation->plant->email ?? '',
        ];

        // Bill To (Patron)
        $data['bill_to'] = [
            'name'    => $quotation->patron->legal_name ?? $quotation->patron->name ?? 'N/A',
            'address' => $quotation->patron->address_line1 ?? '',
            'city'    => $quotation->patron->city ?? '',
            'state'   => $quotation->patron->state ?? '',
            'pin'     => $quotation->patron->pincode ?? '',
            'gstin'   => $quotation->patron->gstin ?? '',
            'phone'   => $quotation->patron->phone ?? '',
        ];

        // Ship To (Site if exists, or Patron)
        $data['ship_to'] = [
            'name'    => $quotation->site->name ?? $data['bill_to']['name'],
            'address' => $quotation->site->address ?? $data['bill_to']['address'],
            'city'    => $quotation->site->city ?? $data['bill_to']['city'],
            'state'   => $quotation->site->state ?? $data['bill_to']['state'],
            'pin'     => $quotation->site->pincode ?? $data['bill_to']['pin'],
        ];

        // Items
        $data['items'] = $quotation->items->map(function ($item, $idx) {
            return [
                'no'           => $idx + 1,
                'name'         => $item->mixDesign->design_name ?? 'N/A',
                'description'  => $item->description ?? $item->mixDesign->design_code ?? '',
                'hsn'          => $item->mixDesign->hsn_code ?? '-',
                'qty'          => (float)$item->quantity,
                'received_qty' => 0,
                'unit'         => $item->mixDesign->unit->unit_code ?? '',
                'unit_price'   => (float)$item->rate,
                'tax_name'     => $item->tax?->tax_name ?? '-',
                'tax_rate'     => (float)($item->tax?->tax_rate ?? 0),
                'tax_group'    => $item->tax?->tax_group ?? '',
                'tax_amount'   => (float)($item->tax_amount ?? 0),
                'total'        => (float)($item->amount_total ?? ($item->quantity * $item->rate)),
            ];
        })->toArray();

        $data['totals'] = [
            'sub_total'   => (float)$quotation->amount_untaxed,
            'discount'    => 0,
            'tax_lines'   => $quotation->tax ? [['label' => $quotation->tax->tax_name, 'amount' => (float)$quotation->tax_amount]] : [],
            'shipping'    => 0,
            'grand_total' => (float)$quotation->amount_total,
        ];

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => $quotation->notes ?? '',
            'terms_text'      => $quotation->terms_conditions ?? '',
            'total_words'     => self::numberToWords($quotation->amount_total, 'INR'),
        ];

        return $data;
    }

    /**
     * Converts a number to words in Indian Numbering System.
     */
    public static function numberToWords($number, $currency = 'INR')
    {
        $no = round($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            '0' => '', '1' => 'One', '2' => 'Two',
            '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
            '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
            '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
            '13' => 'Thirteen', '14' => 'Fourteen',
            '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
            '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty',
            '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
            '60' => 'Sixty', '70' => 'Seventy',
            '80' => 'Eighty', '90' => 'Ninety'
        );
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else $str[] = null;
        }
        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ?
            "and " . $words[$point / 10] . " " .
            $words[$point = $point % 10] . " Paise" : '';
        
        $currency_label = $currency === 'INR' ? 'Rupees ' : $currency . ' ';
        return $currency_label . $result . " " . $points . " Only";
    }

    // ─────────────────────────────────────────────────────
    //  RESOLVE template key from DB settings
    // ─────────────────────────────────────────────────────
    public static function resolveTemplateKey(string $moduleKey, int $plantId): string
    {
        $setting = PrintTemplateSetting::where('module_key', $moduleKey)
            ->where('plant_id', $plantId)
            ->with('template')
            ->first();

        return $setting?->template?->key ?? 'standard';
    }

    // ─────────────────────────────────────────────────────
    //  SUPPORTED TEMPLATE KEYS
    // ─────────────────────────────────────────────────────
    public static function supportedTemplates(): array
    {
        return [
            'standard', 'elite', 'modern', 'spreadsheet', 'tallysheet', 'compact', 'indian_gst',
            'formal_gst', 'standard_indigo', 'minimalist_lite'
        ];
    }

    public static function resolveView(string $templateKey): string
    {
        // Internal mapping for keys that share the same blade file
        $map = [
            'formal_gst'      => 'indian_gst',
            'standard_indigo' => 'elite',
            'minimalist_lite' => 'compact',
        ];

        $supported = self::supportedTemplates();
        $key = in_array($templateKey, $supported) ? ($map[$templateKey] ?? $templateKey) : 'standard';
        
        return "pdfs.templates.{$key}";
    }

    /**
     * Get customization settings for a module.
     */
    public static function getCustomSettings(int $plantId, string $module): array
    {
        $stored = \App\Models\CustomSetting::getForModule($plantId, $module);
        $defaults = self::getDefaultSettings($module);

        return array_replace_recursive($defaults, $stored);
    }

    public static function getDefaultSettings(string $module): array
    {
        $settings = [
            'invoices' => [
                'pdf' => [
                    'company_name'   => true,
                    'logo'           => true,
                    'address'        => true,
                    'phone'          => true,
                    'email'          => true,
                    'gstin'          => true,
                    'invoice_title'  => true,
                    'invoice_number' => true,
                    'date'           => true,
                    'due_date'       => true,
                    'status'         => false,
                    'bill_to'        => true,
                    'ship_to'        => true,
                    'hsn_code'       => true,
                    'description'    => true,
                    'unit'           => true,
                    'discount'       => true,
                    'tax_percent'    => true,
                    'cgst'           => true,
                    'sgst'           => true,
                    'igst'           => true,
                    'shipping'       => true,
                    'round_off'      => true,
                    'total_words'    => true,
                    'notes'          => true,
                    'terms'          => true,
                    'signature'      => true,
                    'labels' => [
                        'invoice_title' => 'TAX INVOICE',
                        'bill_to'       => 'Bill To',
                        'ship_to'       => 'Ship To',
                        'rate'          => 'Rate',
                        'amount'        => 'Amount',
                    ]
                ],
                'excel' => [
                    'hsn_code' => true,
                    'discount' => true,
                ]
            ],
            'purchase_orders' => [
                'pdf' => [
                    'company_name' => true,
                    'logo'         => true,
                    'terms'        => true,
                    'signature'    => true,
                ]
            ]
        ];

        return $settings[$module] ?? [];
    }
}
