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
                'adjustment'  => 0,
                'round_off'   => 0,
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
            'adjustment'  => 0,
            'round_off'   => 0,
            'grand_total' => 405900.00,
        ];

        $data['meta']['total_words']    = 'Indian Rupee Four Lakh Five Thousand Nine Hundred Only';
        $data['meta']['project_name']   = 'Grand Mall Construction - Phase 1';
        $data['meta']['po_number']     = 'PO-8877';
        $data['meta']['terms_text']    = "1. Payment within 15 days of delivery.\n2. Goods once sold will not be taken back.\n3. Subject to Chennai Jurisdiction.";

        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  RESOLVE TERMS CONDITION
    // ─────────────────────────────────────────────────────
    public static function resolveTermsCondition(array $settings, string $orderType, int $plantId, ?string $fallbackTerms = null): string
    {
        if (empty($settings['pdf']['terms'])) {
            return '';
        }

        $tc = \App\Models\TermsCondition::where('plant_id', $plantId)
            ->where('order_type', $orderType)
            ->where('status', 'active')
            ->first();
            
        if ($tc) return $tc->terms_condition;
        
        return $fallbackTerms ?? '';
    }

    // ─────────────────────────────────────────────────────
    //  PURCHASE ORDER
    // ─────────────────────────────────────────────────────
    public static function fromPurchaseOrder($order): array
    {
        $order->loadMissing(['items.product', 'items.uom', 'items.tax', 'vendor', 'plant', 'plant.entity', 'plant.addresses', 'currency']);

        $data = self::base();
        $data['settings'] = self::getCustomSettings($order->plant_id, 'purchase_orders');

        // Document meta
        $data['doc_title']     = $data['settings']['pdf']['labels']['invoice_title'] ?? 'PURCHASE ORDER';
        $data['doc_no']        = $order->ref_no;
        $data['doc_date']      = $order->date_order?->format('d/m/Y') ?? 'N/A';
        $data['due_date']      = $order->due_date?->format('d/m/Y') ?? 'N/A';
        $data['delivery_date'] = $order->date_planned?->format('d/m/Y') ?? 'N/A';
        $data['state']         = strtoupper($order->state ?? 'DRAFT');

        // Company (plant as issuer)
        $plAddr = $order->plant->addresses->first();
        $data['company'] = [
            'name'    => $order->plant->entity->entity_name ?? $order->plant->name,
            'address' => $plAddr->line_1 ?? '',
            'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '',
            'pin'     => $plAddr->zipcode ?? '',
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
            'address' => $plAddr->line_1 ?? '',
            'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '',
            'pin'     => $plAddr->zipcode ?? '',
        ];

        $plantGstin = $order->plant->gstin ?? '';
        $vendorGstin = $order->vendor->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $vendorState = strlen($vendorGstin) >= 2 ? substr($vendorGstin, 0, 2) : '';
        $isIntra = true;
        if (strlen($vendorState) >= 2 && $vendorState !== $plantState) {
            $isIntra = false;
        }

        // Items
        $data['items'] = $order->items->map(function ($item, $idx) use ($isIntra) {
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->price_tax;

            if ($taxRate <= 0 && $priceTax > 0 && (float)$item->price_subtotal > 0) {
                $taxRate = round(($priceTax / (float)$item->price_subtotal) * 100, 2);
                if ($isIntra) {
                    $taxGroup = 'GST';
                    $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                } else {
                    $taxGroup = 'IGST';
                    $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                }
            }

            return [
                'no'           => $idx + 1,
                'name'         => $item->product->title,
                'description'  => $item->description ?? '',
                'hsn'          => $item->product->hsn_code ?? '-',
                'qty'          => (float)$item->product_quantity,
                'received_qty' => (float)($item->received_quantity ?? 0),
                'unit'         => $item->uom->unit_code ?? '',
                'unit_price'   => (float)$item->unit_price,
                'tax_name'     => $taxName ?: '-',
                'tax_rate'     => $taxRate,
                'tax_group'    => $taxGroup,
                'tax_amount'   => $priceTax,
                'total'        => (float)$item->price_total,
            ];
        })->toArray();

        // Tax lines summary
        $taxLines = [];
        foreach ($order->items as $item) {
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $priceTax = (float)$item->price_tax;

            if ($priceTax <= 0 && !$taxModel) continue;

            if ($taxRate <= 0 && (float)$item->price_subtotal > 0) {
                $taxRate = round(($priceTax / (float)$item->price_subtotal) * 100, 2);
                $taxGroup = $isIntra ? 'GST' : 'IGST';
            }

            if (empty($taxGroup)) {
                $taxGroup = $isIntra ? 'GST' : 'IGST';
            }

            $g = strtoupper(trim($taxGroup));
            if ($g === 'GST') {
                $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2);
                $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2);
            } else {
                $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax;
            }
        }

        $data['totals'] = [
            'sub_total'   => (float)$order->amount_untaxed,
            'discount'    => (float)($order->discount_amount ?? 0),
            'tax_lines'   => collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray(),
            'shipping'    => (float)($order->shipping_charges ?? 0),
            'adjustment'  => (float)($order->adjustment ?? 0),
            'round_off'   => (float)($order->round_off ?? 0),
            'grand_total' => (float)$order->amount_total,
        ];

        // Meta
        $data['meta'] = [
            'po_number'       => $order->po_number ?? $order->ref_no,
            'project_name'    => $order->plant->name,
            'currency_code'   => $order->currency->currency_code ?? 'INR',
            'currency_symbol' => $order->currency->currency_symbol ?? '₹',
            'notes'           => $order->notes ?? '',
            'terms_text'      => self::resolveTermsCondition($data['settings'], 'Purchase Order', $order->plant_id, $order->terms_conditions ?? ''),
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
        $invoice->loadMissing(['plant', 'plant.entity', 'plant.addresses', 'partner', 'items.tax', 'items.uom', 'orderTaxes']);

        // For purchase bills, load the originating PO to display the PO number
        $linkedPO = null;
        if ($invoice->invoice_type === 'bill' && !empty($invoice->ref_id)) {
            $linkedPO = \App\Models\PurchaseOrder::find($invoice->ref_id);
        }

        $data = self::base();
        $data['settings'] = self::getCustomSettings($invoice->plant_id, 'invoices');

        $defaultTitle = $invoice->invoice_type === 'bill' ? 'PURCHASE BILL' : 'TAX INVOICE';
        $docTitle = $data['settings']['pdf']['labels']['invoice_title'] ?? $defaultTitle;

        if (!empty($invoice->invoice_label)) {
            if (strtolower($invoice->invoice_label) === 'manual') {
                $docTitle = 'MANUAL BILLING';
            } else {
                $docTitle = strtoupper($invoice->invoice_label);
            }
        }

        $data['doc_title'] = $docTitle;
        $data['doc_no']    =  $invoice->prefix . $invoice->invoice_number;
        $data['doc_date']  = $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $invoice->due_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($invoice->status ?? 'DRAFT');

        // Company
        $plAddr = $invoice->plant->addresses->first();
        $data['company'] = [
            'name'    => $invoice->plant->entity->entity_name ?? $invoice->plant->name ?? 'Company',
            'address' => $plAddr->line_1 ?? '',
            'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '',
            'pin'     => $plAddr->zipcode ?? '',
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

        $plantGstin = $invoice->plant->gstin ?? '';
        $partnerGstin = $invoice->partner->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $partnerState = strlen($partnerGstin) >= 2 ? substr($partnerGstin, 0, 2) : '';
        $isIntra = true;
        if (strlen($partnerState) >= 2 && $partnerState !== $plantState) {
            $isIntra = false;
        }

        // Items
        $data['items'] = $invoice->items->map(function ($item, $idx) use ($isIntra) {
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->line_tax_amount;

            if ($taxRate <= 0 && $priceTax > 0 && (float)$item->subtotal > 0) {
                $taxRate = round(($priceTax / (float)$item->subtotal) * 100, 2);
                if ($isIntra) {
                    $taxGroup = 'GST';
                    $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                } else {
                    $taxGroup = 'IGST';
                    $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                }
            }

            return [
                'no'           => $idx + 1,
                'name'         => $item->item_name,
                'description'  => $item->hsn_code ? 'HSN: ' . $item->hsn_code : '',
                'hsn'          => $item->hsn_code ?? '-',
                'qty'          => (float)$item->quantity,
                'unit'         => $item->uom->unit_code ?? 'm³',
                'unit_price'   => (float)$item->price_unit,
                'tax_name'     => $taxName ?: '-',
                'tax_rate'     => $taxRate,
                'tax_group'    => $taxGroup,
                'tax_amount'   => $priceTax,
                'total'        => (float)($item->line_total ?? ($item->quantity * $item->price_unit)),
            ];
        })->toArray();

        // Totals
        $taxLines = $invoice->orderTaxes->map(function($ot) {
            return ['label' => $ot->name, 'amount' => (float)$ot->amount];
        })->toArray();

        $data['totals'] = [
            'sub_total'   => (float)$invoice->subtotal,
            'discount'    => (float)($invoice->discount_total ?? $invoice->global_discount),
            'tax_lines'   => $taxLines,
            'shipping'    => (float)$invoice->shipping_charges,
            'adjustment'  => (float)$invoice->adjustment,
            'round_off'   => (float)$invoice->round_off,
            'grand_total' => (float)$invoice->total_amount,
        ];

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => '',
            'terms_text'      => self::resolveTermsCondition($data['settings'], $invoice->invoice_type === 'bill' ? 'Purchase Bill' : 'Tax Invoice', $invoice->plant_id, "1. Goods once sold will not be taken back.\n2. Interest @ 18% will be charged if not paid within due date.\n3. All disputes are subject to local jurisdiction."),
            'total_words'     => self::numberToWords($invoice->total_amount, 'INR'),
            'po_number'       => $invoice->ref_id ?? '',
            'project_name'    => $invoice->ref_title ?? '',
        ];
// dd($data);
        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  QUOTATION
    // ─────────────────────────────────────────────────────
    public static function fromQuotation($quotation): array
    {
        $quotation->loadMissing([
            'items.mixDesign',
            'items.mixDesign.items.product',
            'items.mixDesign.items.uom',
            'items.mixDesign.unit',
            'items.tax',
            'patron',
            'plant',
            'plant.entity',
            'plant.addresses',
            'tax'
        ]);

        $data = self::base();
        $data['settings'] = self::getCustomSettings($quotation->plant_id, 'quotations');
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'QUOTATION';
        $data['doc_no']    = $quotation->reference ?? $quotation->id;
        $data['doc_date']  = $quotation->quote_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $quotation->validity_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($quotation->status_text ?? 'DRAFT');

        // Company
        $plAddr = $quotation->plant->addresses->first();
        $data['company'] = [
            'name'    => $quotation->plant->entity->entity_name ?? $quotation->plant->name,
            'address' => $plAddr->line_1 ?? '',
            'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '',
            'pin'     => $plAddr->zipcode ?? '',
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

        $plantGstin = $quotation->plant->gstin ?? '';
        $patronGstin = $quotation->patron->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $patronState = strlen($patronGstin) >= 2 ? substr($patronGstin, 0, 2) : '';
        $isIntra = true;
        if (strlen($patronState) >= 2 && $patronState !== $plantState) {
            $isIntra = false;
        }

        // Items
        $data['items'] = $quotation->items->map(function ($item, $idx) use ($isIntra) {
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->tax_amount;
            $subtotal = (float)($item->quantity * $item->rate);

            if ($taxRate <= 0 && $priceTax > 0 && $subtotal > 0) {
                $taxRate = round(($priceTax / $subtotal) * 100, 2);
                if ($isIntra) {
                    $taxGroup = 'GST';
                    $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                } else {
                    $taxGroup = 'IGST';
                    $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                }
            }

            $description = $item->description ?? $item->mixDesign->design_code ?? '';
            if ($item->mixDesign && $item->mixDesign->items && $item->mixDesign->items->count() > 0) {
                $materials = $item->mixDesign->items->map(function ($mdItem) {
                    $prodName = $mdItem->product->title ?? 'Unknown';
                    $qty = (float)$mdItem->actual_quantity;
                    $unit = $mdItem->uom->unit_code ?? '';
                    return trim("{$prodName} ({$qty} {$unit})");
                })->filter()->implode(', ');
                
                if ($materials) {
                    $description .= $description ? "\nMaterials: {$materials}" : "Materials: {$materials}";
                }
            }

            return [
                'no'           => $idx + 1,
                'name'         => $item->mixDesign->design_name ?? 'N/A',
                'description'  => $description,
                'hsn'          => $item->mixDesign->hsn_code ?? '-',
                'qty'          => (float)$item->quantity,
                'received_qty' => 0,
                'unit'         => $item->mixDesign->unit->unit_code ?? '',
                'unit_price'   => (float)$item->rate,
                'tax_name'     => $taxName ?: '-',
                'tax_rate'     => $taxRate,
                'tax_group'    => $taxGroup,
                'tax_amount'   => $priceTax,
                'total'        => (float)($item->amount_total ?? ($item->quantity * $item->rate)),
            ];
        })->toArray();

        $taxLines = [];
        foreach ($quotation->items as $item) {
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $priceTax = (float)$item->tax_amount;
            $subtotal = (float)($item->quantity * $item->rate);

            if ($priceTax <= 0 && !$taxModel) continue;

            if ($taxRate <= 0 && $subtotal > 0) {
                $taxRate = round(($priceTax / $subtotal) * 100, 2);
                $taxGroup = $isIntra ? 'GST' : 'IGST';
            }

            if (empty($taxGroup)) {
                $taxGroup = $isIntra ? 'GST' : 'IGST';
            }

            $g = strtoupper(trim($taxGroup));
            if ($g === 'GST') {
                $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2);
                $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2);
            } else {
                $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax;
            }
        }

        $computedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
        $finalTaxLines = $quotation->tax ? [['label' => $quotation->tax->tax_name, 'amount' => (float)$quotation->tax_amount]] : $computedTaxLines;

        $data['totals'] = [
            'sub_total'   => (float)$quotation->amount_untaxed,
            'discount'    => 0,
            'tax_lines'   => $finalTaxLines,
            'shipping'    => 0,
            'adjustment'  => (float)($quotation->adjustment ?? 0),
            'round_off'   => (float)($quotation->round_off ?? 0),
            'grand_total' => (float)$quotation->amount_total,
        ];

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => $quotation->notes ?? '',
            'terms_text'      => self::resolveTermsCondition($data['settings'], 'Quotation', $quotation->plant_id, $quotation->terms_conditions ?? ''),
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
            'formal_gst', 'standard_indigo', 'minimalist_lite', 'delivery_challan_a4'
        ];
    }

    public static function resolveView(string $templateKey): string
    {
        if ($templateKey === 'delivery_challan_a4') {
            return "pdfs.batches.delivery_token";
        }

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
        $invoiceTitle = 'DOCUMENT';
        switch ($module) {
            case 'invoices':
            case 'gst_invoices':
                $invoiceTitle = 'TAX INVOICE';
                break;
            case 'purchase_orders':
                $invoiceTitle = 'PURCHASE ORDER';
                break;
            case 'purchase_bills':
                $invoiceTitle = 'PURCHASE BILL';
                break;
            case 'quotations':
                $invoiceTitle = 'QUOTATION';
                break;
            case 'delivery_challans':
                $invoiceTitle = 'DELIVERY CHALLAN';
                break;
            case 'delivery_notes':
                $invoiceTitle = 'DELIVERY NOTE';
                break;
            case 'credit_notes':
                $invoiceTitle = 'CREDIT NOTE';
                break;
            case 'statements':
                $invoiceTitle = 'STATEMENT OF ACCOUNT';
                break;
        }

        return [
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
                'adjustment'     => true,
                'round_off'      => true,
                'total_words'    => true,
                'notes'          => true,
                'terms'          => true,
                'signature'      => true,
                'labels' => [
                    'invoice_title' => $invoiceTitle,
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
        ];
    }

    public static function fromDeliveryChallan($batch): array
    {
        $batch->loadMissing([
            'salesOrder',
            'salesOrder.customer',
            'salesOrder.site',
            'salesOrder.plant',
            'salesOrder.plant.entity',
            'salesOrder.plant.addresses',
            'salesOrder.mixDesign',
            'salesOrder.mixDesign.concrete_grade',
            'salesOrder.mixDesign.unit',
            'dispatches',
            'dispatches.truck',
            'dispatches.driver',
            'dispatches.transport',
            'materials.product',
            'materials.uom',
            'operator'
        ]);

        $data = self::base();
        $data['settings'] = self::getCustomSettings($batch->salesOrder->plant_id, 'delivery_challans');
        
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'DELIVERY CHALLAN';
        $data['doc_no']    = 'B' . ($batch->batch_no ?? $batch->id);
        $data['doc_date']  = optional($batch->load_time ?? $batch->created_at)->format('d/m/Y H:i');
        
        $dispatch = $batch->dispatches->first();
        $data['delivery_date'] = $dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('d/m/Y H:i') : ($batch->load_time ? $batch->load_time->format('d/m/Y H:i') : 'N/A');
        
        $data['state']     = $batch->status_text ?? 'DISPATCHED';

        // Company (Issuer Plant)
        $plant = $batch->salesOrder->plant;
        $plAddr = $plant->addresses->first();
        $data['company'] = [
            'name'    => $plant->name,
            'address' => $plAddr->line_1 ?? '',
            'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '',
            'pin'     => $plAddr->zipcode ?? '',
            'gstin'   => $plant->gstin ?? '',
            'phone'   => $plant->phone ?? '',
            'email'   => $plant->email ?? '',
        ];

        // Bill To (Customer)
        $customer = $batch->salesOrder->customer;
        $data['bill_to'] = [
            'name'    => $customer->legal_name ?? $customer->name ?? 'N/A',
            'address' => $customer->address_line1 ?? '',
            'city'    => $customer->city ?? '',
            'state'   => $customer->state ?? '',
            'pin'     => $customer->pincode ?? '',
            'gstin'   => $customer->gstin ?? '',
            'phone'   => $customer->phone ?? '',
        ];

        // Ship To (Site)
        $site = $batch->salesOrder->site;
        $data['ship_to'] = [
            'name'    => $site->name ?? $data['bill_to']['name'],
            'address' => $site->site_address_1 ?? $data['bill_to']['address'],
            'city'    => $site->city ?? $data['bill_to']['city'],
            'state'   => $site->state ?? $data['bill_to']['state'],
            'pin'     => $site->zipcode ?? $data['bill_to']['pin'],
        ];

        // Items (Batch Materials)
        $data['items'] = $batch->materials->map(function ($item, $idx) {
            $target = (float) $item->target_qty;
            $actual = (float) $item->actual_qty;
            $deviationVal = (float) $item->deviation_quantity;
            $devPercent = 0;
            if ($target > 0) {
                $devPercent = ($deviationVal / $target) * 100;
            }

            $devSign = $devPercent > 0 ? '+' : '';
            $desc = sprintf(
                "Target: %s %s | Actual: %s %s | Dev: %s%s%%",
                number_format($target, 2),
                $item->uom?->unit_code ?? 'kg',
                number_format($actual, 2),
                $item->uom?->unit_code ?? 'kg',
                $devSign,
                number_format($devPercent, 2)
            );

            return [
                'no'           => $idx + 1,
                'name'         => $item->material_name ?: ($item->product->title ?? 'Material'),
                'description'  => $desc,
                'hsn'          => $item->product->hsn_code ?? '-',
                'qty'          => $actual ?: $target,
                'received_qty' => $actual,
                'unit'         => $item->uom?->unit_code ?? 'kg',
                'unit_price'   => 0.00,
                'tax_name'     => '-',
                'tax_rate'     => 0.00,
                'tax_amount'   => 0.00,
                'total'        => 0.00,
            ];
        })->toArray();

        // Totals (set to 0 since Delivery Challan is non-financial)
        $data['totals'] = [
            'sub_total'   => 0.00,
            'discount'    => 0.00,
            'tax_lines'   => [],
            'shipping'    => 0.00,
            'adjustment'  => 0.00,
            'round_off'   => 0.00,
            'grand_total' => 0.00,
        ];

        // Metadata & Weights
        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');
        $isMetricTon = !empty($settings['InvoiceInMetricTon']) && $settings['InvoiceInMetricTon'] == 1;
        $emptyWeight = (float) ($dispatch?->empty_weight_truck ?? 0);
        $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
        $netWeight = (float) ($dispatch?->net_weight ?? ($loadedWeight - $emptyWeight));
        
        $unitLabel = $isMetricTon ? ' MT' : ' kg';
        $decimals = $isMetricTon ? 3 : 0;
        
        $emptyWeightStr = number_format($emptyWeight, $decimals) . $unitLabel;
        $loadedWeightStr = number_format($loadedWeight, $decimals) . $unitLabel;
        $netWeightStr = number_format($netWeight, $decimals) . $unitLabel;

        $weightNotes = "VEHICLE WEIGHT DETAILS:\n"
            . "Truck No: " . ($dispatch?->truck?->registration ?? '-') . "\n"
            . "Driver: " . (trim(($dispatch?->driver?->first_name ?? '') . ' ' . ($dispatch?->driver?->last_name ?? '')) ?: '-') . "\n"
            . "Empty Weight: " . $emptyWeightStr . " (" . ($dispatch?->empty_time ? \Carbon\Carbon::parse($dispatch->empty_time)->format('d-m-Y H:i') : '-') . ")\n"
            . "Loaded Weight: " . $loadedWeightStr . " (" . ($dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('d-m-Y H:i') : '-') . ")\n"
            . "Net Weight: " . $netWeightStr . "\n\n"
            . "Batch size: " . number_format((float) $batch->batch_size, 2) . " m³\n"
            . "Concrete Grade: " . ($batch->salesOrder?->mixDesign?->concrete_grade?->name ?? ($batch->salesOrder?->mixDesign?->design_name ?? '-')) . " / Recipe Code: " . ($batch->salesOrder?->mixDesign?->design_code ?? '-');

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => $weightNotes,
            'terms_text'      => self::resolveTermsCondition($data['settings'], 'Delivery Challan', $batch->salesOrder->plant_id, $batch->salesOrder?->terms_conditions ?? "1. Goods received in good condition.\n2. Any variation in quantity to be reported immediately."),
            'total_words'     => '',
            'po_number'       => $batch->salesOrder?->order_no ?? '-',
            'project_name'    => 'Concrete Grade: ' . ($batch->salesOrder?->mixDesign?->concrete_grade?->name ?? '-'),
        ];

        $data['batch'] = $batch;

        return $data;
    }
}