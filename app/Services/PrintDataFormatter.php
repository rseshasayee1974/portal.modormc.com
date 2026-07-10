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
            'state'        => 'DRAFT',
            'terms'        => 'Net 30',
            'company' => [
                'name'    => '', 'address' => '', 'city' => '', 'state' => '', 'pin' => '', 'gstin' => '', 'phone' => '', 'email' => '',
            ],
            'bill_to' => [
                'name'    => '', 'address' => '', 'city' => '', 'state' => '', 'pin' => '', 'gstin' => '', 'phone' => '',
            ],
            'ship_to' => [
                'name'    => '', 'address' => '', 'city' => '', 'state' => '', 'pin' => '',
            ],
            'items' => [],
            'totals' => [
                'sub_total'   => 0, 'discount'    => 0, 'tax_lines'   => [], 'shipping'    => 0, 'adjustment'  => 0, 'round_off'   => 0, 'grand_total' => 0,
            ],
            'meta' => [
                'po_number'       => '', 'project_name'    => '', 'currency_code'   => 'INR', 'currency_symbol' => '₹',
                'notes'           => '', 'terms_text'      => '', 'total_words'     => '', 'site_incharge'   => '', 'contact_no'      => '',
            ],
        ];
    }

    public static function dummy(string $category = 'invoice'): array
    {
        $data = self::base();
        $data['doc_title'] = strtoupper($category) . ' DOCUMENT';
        $data['doc_no']    = 'REF-2026-001';
        $data['doc_date']  = now()->format('d/m/Y');
        $data['due_date']  = now()->addDays(15)->format('d/m/Y');
        $data['delivery_date'] = now()->addDays(5)->format('d/m/Y');
        $data['company'] = [
            'name'    => 'ModoMines Tech Solutions', 'address' => '123 Cloud Avenue, Tech Park', 'city'    => 'Chennai',
            'state'   => 'Tamil Nadu', 'pin'     => '600001', 'gstin'   => '33AAAAA0000A1Z5', 'phone'   => '+91 98765 43210', 'email'   => 'support@modomines.com',
        ];
        $data['bill_to'] = [
            'name'    => 'Alpha Prime Industries', 'address' => '45 Industrial Estate, Phase II', 'city'    => 'Coimbatore',
            'state'   => 'Tamil Nadu', 'pin'     => '641001', 'gstin'   => '33BBBBB1111B1Z2', 'phone'   => '+91 422 2345678',
        ];
        $data['ship_to'] = [
            'name'    => 'Alpha Prime - Site A', 'address' => 'Plot 88, Near New Bypass', 'city'    => 'Salem', 'state'   => 'Tamil Nadu', 'pin'     => '636001',
        ];
        $data['items'] = [
            [
                'no' => 1, 'name' => 'High Grade Concrete Mix (M40)', 'description'  => 'Standard grade for heavy structural works',
                'hsn' => '382450', 'qty' => 45.00, 'received_qty' => 45.00, 'unit' => 'm³', 'unit_price' => 4500.00,
                'tax_name' => 'GST 12%', 'tax_rate' => 12, 'tax_group' => 'GST', 'tax_amount' => 24300.00, 'total' => 226800.00,
            ],
            [
                'no' => 2, 'name' => 'Reinforcement Steel (12mm)', 'description'  => 'TMT Bars - FE500D Grade',
                'hsn' => '721420', 'qty' => 2.50, 'received_qty' => 0.00, 'unit' => 'MT', 'unit_price' => 62000.00,
                'tax_name' => 'GST 18%', 'tax_rate' => 18, 'tax_group' => 'GST', 'tax_amount' => 27900.00, 'total' => 182900.00,
            ]
        ];
        $data['totals'] = [
            'sub_total'   => 357500.00, 'discount'    => 5000.00,
            'tax_lines'   => [['label' => 'CGST', 'amount' => 26100.00], ['label' => 'SGST', 'amount' => 26100.00]],
            'shipping'    => 1200.00, 'adjustment'  => 0, 'round_off'   => 0, 'grand_total' => 405900.00,
        ];
        $data['meta']['total_words']    = 'Rupees Four Lakh Five Thousand Nine Hundred Only';
        $data['meta']['project_name']   = 'Grand Mall Construction - Phase 1';
        $data['meta']['po_number']     = 'PO-8877';
        $data['meta']['terms_text']    = "1. Payment within 15 days of delivery.\n2. Goods once sold will not be taken back.\n3. Subject to Chennai Jurisdiction.";
        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  RESOLVE TERMS CONDITION
    // ─────────────────────────────────────────────────────
    public static function resolveTermsCondition(array $settings, $orderType, int $plantId, ?string $fallbackTerms = null): string
    {
        if (empty($settings['pdf']['terms'])) return '';
        if (!empty($settings['pdf']['terms_text'])) return (string) $settings['pdf']['terms_text'];
        $orderTypes = is_array($orderType) ? $orderType : [$orderType];
        foreach ($orderTypes as $type) {
            $tc = \App\Models\TermsCondition::where('plant_id', $plantId)->where('order_type', $type)->where('status', 'active')->first();
            if ($tc) return $tc->terms_condition;
        }
        foreach ($orderTypes as $type) {
            $tc = \App\Models\TermsCondition::where('plant_id', 0)->where('order_type', $type)->where('status', 'active')->first();
            if ($tc) return $tc->terms_condition;
        }
        return $fallbackTerms ?? '';
    }

    public static function fromPurchaseOrder($order): array
    {
        $order->loadMissing(['items.product', 'items.uom', 'items.tax', 'vendor', 'plant', 'plant.entity', 'plant.addresses', 'currency']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($order->plant_id, 'purchase_orders');
        $data['doc_title']     = $data['settings']['pdf']['labels']['invoice_title'] ?? 'PURCHASE ORDER';
        $data['doc_no']        = $order->ref_no;
        $data['doc_date']      = $order->date_order?->format('d/m/Y') ?? 'N/A';
        $data['due_date']      = $order->due_date?->format('d/m/Y') ?? 'N/A';
        $data['delivery_date'] = $order->date_planned?->format('d/m/Y') ?? 'N/A';
        $data['state']         = strtoupper($order->state ?? 'DRAFT');
        $plAddr = $order->plant->addresses->first();
        $data['company'] = [
            'name'    => $order->plant->entity->entity_name ?? $order->plant->name,
            'address' => $plAddr->line_1 ?? '', 'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '', 'pin'     => $plAddr->zipcode ?? '',
            'gstin'   => $order->plant->gstin ?? '', 'phone'   => $order->plant->phone ?? '', 'email'   => $order->plant->email ?? '',
        ];
        $data['bill_to'] = [
            'name'    => $order->vendor->legal_name, 'address' => $order->vendor->address_line1,
            'city'    => $order->vendor->city ?? '', 'state'   => $order->vendor->state ?? '',
            'pin'     => $order->vendor->pincode ?? '', 'gstin'   => $order->vendor->gstin ?? '', 'phone'   => $order->vendor->phone ?? '',
        ];
        $data['ship_to'] = [
            'name'    => $order->plant->entity->entity_name ?? $order->plant->name,
            'address' => $plAddr->line_1 ?? '', 'city'    => $plAddr->city ?? '',
            'state'   => $plAddr->state->state_name ?? $plAddr->state_code ?? '', 'pin'     => $plAddr->zipcode ?? '',
        ];
        $plantGstin = $order->plant->gstin ?? ''; $vendorGstin = $order->vendor->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $vendorState = strlen($vendorGstin) >= 2 ? substr($vendorGstin, 0, 2) : '';
        $isIntra = true; if (strlen($vendorState) >= 2 && $vendorState !== $plantState) $isIntra = false;
        $data['items'] = $order->items->map(function ($item, $idx) use ($isIntra) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->price_tax;
            if ($taxRate <= 0 && $priceTax > 0 && (float)$item->price_subtotal > 0) {
                $taxRate = round(($priceTax / (float)$item->price_subtotal) * 100, 2);
                if ($isIntra) { $taxGroup = 'GST'; $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
                else { $taxGroup = 'IGST'; $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
            }
            return [
                'no' => $idx + 1, 'name' => $item->product->title ?? '', 'description' => $item->description ?? '',
                'hsn' => $item->product->hsn_code ?? '-', 'qty' => (float)$item->product_quantity,
                'received_qty' => (float)($item->received_quantity ?? 0), 'unit' => $item->uom->unit_code ?? '',
                'unit_price' => (float)$item->unit_price, 'tax_name' => $taxName ?: '-', 'tax_rate' => $taxRate,
                'tax_group' => $taxGroup, 'tax_amount' => $priceTax, 'total' => (float)$item->price_total,
            ];
        })->toArray();

        // Tax lines summary
        $taxLines = [];
        foreach ($order->items as $item) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $priceTax = (float)$item->price_tax;
            if ($priceTax <= 0 && !$taxModel) continue;
            if ($taxRate <= 0 && (float)$item->price_subtotal > 0) { $taxRate = round(($priceTax / (float)$item->price_subtotal) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST'; }
            if (empty($taxGroup)) $taxGroup = $isIntra ? 'GST' : 'IGST';
            $g = strtoupper(trim($taxGroup));
            if ($g === 'GST') { $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2); $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2); }
            else { $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax; }
        }
        $data['totals'] = [
            'sub_total'   => (float)$order->amount_untaxed, 'discount'    => (float)($order->discount_amount ?? 0),
            'tax_lines'   => collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray(),
            'shipping'    => (float)($order->shipping_charges ?? 0), 'adjustment'  => (float)($order->adjustment ?? 0),
            'round_off'   => (float)($order->round_off ?? 0), 'grand_total' => (float)$order->amount_total,
        ];
        $data['meta'] = [
            'po_number' => $order->po_number ?? $order->ref_no, 'project_name' => $order->plant->name,
            'currency_code' => $order->currency->currency_code ?? 'INR', 'currency_symbol' => $order->currency->currency_symbol ?? '₹',
            'notes' => $order->notes ?? '',
            'terms_text' => self::resolveTermsCondition($data['settings'], 'Purchase Order', $order->plant_id, $order->terms_conditions ?? ''),
            'total_words' => self::numberToWords($order->amount_total, $order->currency->currency_code ?? 'INR'),
            'site_incharge' => $order->plant->site_incharge ?? '', 'contact_no' => $order->plant->contact_no ?? '',
            'receipt_status' => (int)$order->receipt_status,
        ];
        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  INVOICE
    // ─────────────────────────────────────────────────────
    public static function fromInvoice($invoice): array
    {
        $invoice->loadMissing(['plant', 'plant.entity', 'plant.addresses', 'partner', 'items.tax', 'items.uom', 'orderTaxes']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($invoice->plant_id, 'invoices');
        $defaultTitle = $invoice->invoice_type === 'bill' ? 'PURCHASE BILL' : 'TAX INVOICE';
        $docTitle = $data['settings']['pdf']['labels']['invoice_title'] ?? $defaultTitle;
        if (!empty($invoice->invoice_label)) {
            if (strtolower($invoice->invoice_label) === 'manual') $docTitle = 'MANUAL BILLING';
            elseif (strtolower($invoice->invoice_label) === 'dispatch') $docTitle = $defaultTitle;
            else $docTitle = strtoupper($invoice->invoice_label);
        }
        $data['doc_title'] = $docTitle;
        $data['doc_no']    =  $invoice->prefix . $invoice->invoice_number;
        $data['doc_date']  = $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $invoice->due_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($invoice->status ?? 'DRAFT');
        $plAddr = $invoice->plant->addresses->first();
        $data['company'] = [
            'name' => $invoice->plant->entity->entity_name ?? $invoice->plant->name ?? 'Company',
            'address' => $plAddr->line_1 ?? '', 'city' => $plAddr->city ?? '',
            'state' => $plAddr->state->state_name ?? $plAddr->state_code ?? '', 'pin' => $plAddr->zipcode ?? '',
            'gstin' => $invoice->plant->gstin ?? '', 'phone' => $invoice->plant->phone ?? '', 'email' => $invoice->plant->email ?? '',
        ];
        $partner = $invoice->partner;
        if (strtolower($invoice->invoice_label ?? '') === 'dispatch' && !empty($invoice->ref_id)) {
            $dispatch = \App\Models\Dispatch::with(['salesOrder.customer', 'salesOrder.site', 'unloadSite'])->find($invoice->ref_id);
            if ($dispatch && $dispatch->salesOrder && (!$partner || empty($partner->legal_name))) $partner = $dispatch->salesOrder->customer;
        }
        $partnerAddr = $partner ? $partner->addresses()->first() : null;
        $data['bill_to'] = [
            'name' => $partner?->legal_name ?: ($partner?->name ?: 'N/A'),
            'address' => $partnerAddr?->line_1 ?: ($partner?->address_line1 ?? ''),
            'city' => $partnerAddr?->city ?: ($partner?->city ?? ''),
            'state' => $partnerAddr?->state?->state_name ?: ($partnerAddr?->state_code ?: ($partner?->state ?? '')),
            'pin' => $partnerAddr?->zipcode ?: ($partner?->pincode ?? ''), 'gstin' => $partner?->gstin ?? '', 'phone' => $partner?->phone ?? '',
        ];
        $data['ship_to'] = $data['bill_to'];
        if (isset($dispatch)) {
            $site = $dispatch->workOrder?->site ?? $dispatch->unloadSite;
            if ($site) {
                $data['ship_to'] = [
                    'name' => $site->name ?: $data['bill_to']['name'], 'address' => $site->site_address_1 ?: $data['bill_to']['address'],
                    'city' => $site->city ?: $data['bill_to']['city'], 'state' => $site->state ?: $data['bill_to']['state'], 'pin' => $site->zipcode ?: $data['bill_to']['pin'],
                ];
            }
        }
        $plantGstin = $invoice->plant->gstin ?? ''; $partnerGstin = $partner?->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $partnerState = strlen($partnerGstin) >= 2 ? substr($partnerGstin, 0, 2) : '';
        $isIntra = true; if (strlen($partnerState) >= 2 && $partnerState !== $plantState) $isIntra = false;
        $data['items'] = $invoice->items->map(function ($item, $idx) use ($isIntra) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->line_tax_amount;
            if ($taxRate <= 0 && $priceTax > 0 && (float)$item->subtotal > 0) {
                $taxRate = round(($priceTax / (float)$item->subtotal) * 100, 2);
                if ($isIntra) { $taxGroup = 'GST'; $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
                else { $taxGroup = 'IGST'; $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
            }
            return [
                'no' => $idx + 1, 'name' => $item->item_name, 'description' => '', 'hsn' => $item->hsn_code ?? '-',
                'qty' => (float)$item->quantity, 'unit' => $item->uom->unit_code ?? 'm³', 'unit_price' => (float)$item->price_unit,
                'tax_name' => $taxName ?: '-', 'tax_rate' => $taxRate, 'tax_group' => $taxGroup, 'tax_amount' => $priceTax,
                'total' => (float)($item->line_total ?? ($item->quantity * $item->price_unit)),
            ];
        })->toArray();
        $taxLines = $invoice->orderTaxes->map(function($ot) { return ['label' => $ot->name, 'amount' => (float)$ot->amount]; })->toArray();
        $data['totals'] = [
            'sub_total' => (float)$invoice->subtotal, 'discount' => (float)($invoice->discount_total ?? $invoice->global_discount),
            'tax_lines' => $taxLines, 'shipping' => (float)$invoice->shipping_charges, 'adjustment' => (float)$invoice->adjustment,
            'round_off' => (float)$invoice->round_off, 'grand_total' => (float)$invoice->total_amount,
        ];
        $orderTypeForTerms = $invoice->invoice_type === 'bill' ? 'Purchase Bill' : [($invoice->invoice_label ?? 'Tax Invoice'), 'Tax Invoice'];
        $data['meta'] = [
            'currency_code' => 'INR', 'currency_symbol' => '₹', 'notes' => '',
            'terms_text' => self::resolveTermsCondition($data['settings'], $orderTypeForTerms, $invoice->plant_id, "1. Goods once sold will not be taken back.\n2. Interest @ 18% will be charged if not paid within due date.\n3. All disputes are subject to local jurisdiction."),
            'total_words' => self::numberToWords($invoice->total_amount, 'INR'), 'po_number' => $invoice->ref_id ?? '', 'project_name' => $invoice->ref_title ?? '',
        ];
        return $data;
    }

    public static function fromQuotation($quotation): array
    {
        $quotation->loadMissing(['items.mixDesign','items.mixDesign.items.product','items.mixDesign.items.uom','items.mixDesign.unit','items.uom','items.tax','patron','plant','plant.entity','plant.addresses','tax']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($quotation->plant_id, 'quotations');
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'QUOTATION';
        $data['doc_no']    = $quotation->reference ?? $quotation->id;
        $data['doc_date']  = $quotation->quote_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $quotation->validity_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($quotation->status_text ?? 'DRAFT');
        $isTaxInclusive    = (bool)($quotation->is_tax_inclusive ?? false);
        $data['is_tax_inclusive'] = $isTaxInclusive;
        $plAddr = $quotation->plant->addresses->first();
        $data['company'] = [
            'name' => $quotation->plant->entity->entity_name ?? $quotation->plant->name, 'address' => $plAddr->line_1 ?? '',
            'city' => $plAddr->city ?? '', 'state' => $plAddr->state->state_name ?? $plAddr->state_code ?? '',
            'pin' => $plAddr->zipcode ?? '', 'gstin' => $quotation->plant->gstin ?? '', 'phone' => $quotation->plant->phone ?? '',
            'email' => $quotation->plant->email ?? '', 'seal_sign_path' => $quotation->plant->seal_sign_path ?? '',
        ];
        $data['bill_to'] = [
            'name' => $quotation->patron->legal_name ?? $quotation->patron->name ?? 'N/A', 'address' => $quotation->patron->address_line1 ?? '',
            'city' => $quotation->patron->city ?? '', 'state' => $quotation->patron->state ?? '',
            'pin' => $quotation->patron->pincode ?? '', 'gstin' => $quotation->patron->gstin ?? '', 'phone' => $quotation->patron->phone ?? '',
        ];
        $data['ship_to'] = [
            'name' => $quotation->site->name ?? $data['bill_to']['name'], 'address' => $quotation->site->address ?? $data['bill_to']['address'],
            'city' => $quotation->site->city ?? $data['bill_to']['city'], 'state' => $quotation->site->state ?? $data['bill_to']['state'],
            'pin' => $quotation->site->pincode ?? $data['bill_to']['pin'],
        ];
        $plantGstin = $quotation->plant->gstin ?? ''; $patronGstin = $quotation->patron->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $patronState = strlen($patronGstin) >= 2 ? substr($patronGstin, 0, 2) : '';
        $isIntra = true; if (strlen($patronState) >= 2 && $patronState !== $plantState) $isIntra = false;
        $data['items'] = $quotation->items->map(function ($item, $idx) use ($isIntra, $isTaxInclusive) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->tax_amount; $subtotal = (float)($item->untaxed_amount ?? ($item->quantity * $item->rate));
            if ($taxRate <= 0 && $priceTax > 0 && $subtotal > 0) {
                $taxRate = round(($priceTax / $subtotal) * 100, 2);
                if ($isIntra) { $taxGroup = 'GST'; $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
                else { $taxGroup = 'IGST'; $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
            }
            $description = $item->description ?? $item->mixDesign->design_code ?? '';
            if ($item->mixDesign && $item->mixDesign->items && $item->mixDesign->items->count() > 0) {
                $materials = $item->mixDesign->items->map(function ($mdItem) {
                    $prodName = $mdItem->product->title ?? 'Unknown'; $qty = (float)$mdItem->actual_quantity;
                    $unit = $mdItem->uom->unit_code ?? ''; $formattedQty = $qty == floor($qty) ? (int)$qty : number_format($qty, 2);
                    return trim("• {$prodName} ({$formattedQty} {$unit})");
                })->filter()->implode("\n");
                if ($materials) $description .= $description ? "\n\nRecipe Details:\n{$materials}" : "Recipe Details:\n{$materials}";
            }
            return [
                'no' => $idx + 1, 'name' => $item->mixDesign->design_name ?? 'N/A', 'description' => $description,
                'hsn' => $item->mixDesign->hsn_code ?? '-', 'qty' => (float)$item->quantity, 'received_qty' => 0,
                'unit' => $item->uom->unit_code ?? $item->mixDesign->unit->unit_code ?? '', 'unit_price' => (float)$item->rate,
                'tax_name' => $taxName ?: '-', 'tax_rate' => $taxRate, 'tax_group' => $taxGroup, 'tax_amount' => $priceTax,
                'total' => (float)($item->amount_total ?? ($item->quantity * $item->rate)),
            ];
        })->toArray();
        $taxLines = [];
        foreach ($quotation->items as $item) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $priceTax = (float)$item->tax_amount; $subtotal = (float)($item->quantity * $item->rate);
            if ($priceTax <= 0 && !$taxModel) continue;
            if ($taxRate <= 0 && $subtotal > 0) { $taxRate = round(($priceTax / $subtotal) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST'; }
            if (empty($taxGroup)) $taxGroup = $isIntra ? 'GST' : 'IGST';
            $g = strtoupper(trim($taxGroup));
            if ($g === 'GST') { $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2); $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2); }
            else { $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax; }
        }
        $computedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
        $finalTaxLines = $quotation->tax ? [['label' => $quotation->tax->tax_name, 'amount' => (float)$quotation->tax_amount]] : $computedTaxLines;
        $data['totals'] = [
            'sub_total' => (float)$quotation->amount_untaxed, 'discount' => 0, 'tax_lines' => $finalTaxLines,
            'shipping' => 0, 'adjustment' => (float)($quotation->adjustment ?? 0), 'round_off' => (float)($quotation->round_off ?? 0),
            'grand_total' => (float)$quotation->amount_total,
        ];
        $data['meta'] = [
            'currency_code' => 'INR', 'currency_symbol' => '₹', 'notes' => $quotation->notes ?? '',
            'terms_text' => self::resolveTermsCondition($data['settings'], 'Quotation', $quotation->plant_id, $quotation->terms_conditions ?? ''),
            'total_words' => self::numberToWords($quotation->amount_total, 'INR'),
        ];
        return $data;
    }

    public static function fromCustomerPO($customerPO): array
    {
        $customerPO->loadMissing(['items.mixDesign','items.mixDesign.items.product','items.mixDesign.items.uom','items.mixDesign.unit','items.tax','patron','plant','plant.entity','plant.addresses','site']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($customerPO->plant_id, 'customer_pos') ?: self::getCustomSettings($customerPO->plant_id, 'quotations');
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'CUSTOMER PO';
        $data['doc_no']    = $customerPO->reference ?? $customerPO->id;
        $data['doc_date']  = $customerPO->order_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $customerPO->due_date?->format('d/m/Y') ?? '';
        $statusText = 'DRAFT'; if ($customerPO->status == 1) $statusText = 'CONFIRMED'; if ($customerPO->status == 2) $statusText = 'COMPLETED';
        $data['state'] = $statusText;
        $isTaxInclusive = (bool)($customerPO->is_tax_inclusive ?? false); $data['is_tax_inclusive'] = $isTaxInclusive;
        $plAddr = $customerPO->plant->addresses->first();
        $data['company'] = [
            'name' => $customerPO->plant->entity->entity_name ?? $customerPO->plant->name, 'address' => $plAddr->line_1 ?? '',
            'city' => $plAddr->city ?? '', 'state' => $plAddr->state->state_name ?? $plAddr->state_code ?? '', 'pin' => $plAddr->zipcode ?? '',
            'gstin' => $customerPO->plant->gstin ?? '', 'phone' => $customerPO->plant->phone ?? '', 'email' => $customerPO->plant->email ?? '',
            'seal_sign_path' => $customerPO->plant->seal_sign_path ?? '',
        ];
        $data['bill_to'] = [
            'name' => $customerPO->patron->legal_name ?? $customerPO->patron->name ?? 'N/A', 'address' => $customerPO->patron->address_line1 ?? '',
            'city' => $customerPO->patron->city ?? '', 'state' => $customerPO->patron->state ?? '', 'pin' => $customerPO->patron->pincode ?? '',
            'gstin' => $customerPO->patron->gstin ?? '', 'phone' => $customerPO->patron->phone ?? '',
        ];
        $data['ship_to'] = [
            'name' => $customerPO->site->name ?? $data['bill_to']['name'], 'address' => $customerPO->site->address ?? $data['bill_to']['address'],
            'city' => $customerPO->site->city ?? $data['bill_to']['city'], 'state' => $customerPO->site->state ?? $data['bill_to']['state'],
            'pin' => $customerPO->site->pincode ?? $data['bill_to']['pin'],
        ];
        $plantGstin = $customerPO->plant->gstin ?? ''; $patronGstin = $customerPO->patron->gstin ?? '';
        $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $patronState = strlen($patronGstin) >= 2 ? substr($patronGstin, 0, 2) : '';
        $isIntra = true; if (strlen($patronState) >= 2 && $patronState !== $plantState) $isIntra = false;
        $data['items'] = $customerPO->items->map(function ($item, $idx) use ($isIntra, $isTaxInclusive) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';
            $priceTax = (float)$item->tax_amount; $subtotal = (float)($item->untaxed_amount ?? ($item->quantity * $item->rate));
            if ($taxRate <= 0 && $priceTax > 0 && $subtotal > 0) {
                $taxRate = round(($priceTax / $subtotal) * 100, 2);
                if ($isIntra) { $taxGroup = 'GST'; $taxName  = 'GST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
                else { $taxGroup = 'IGST'; $taxName  = 'IGST ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
            }
            $description = $item->description ?? $item->mixDesign->design_code ?? '';
            if ($item->mixDesign && $item->mixDesign->items && $item->mixDesign->items->count() > 0) {
                $materials = $item->mixDesign->items->map(function ($mdItem) {
                    $prodName = $mdItem->product->title ?? 'Unknown'; $qty = (float)$mdItem->actual_quantity;
                    $unit = $mdItem->uom->unit_code ?? ''; $formattedQty = $qty == floor($qty) ? (int)$qty : number_format($qty, 2);
                    return trim("• {$prodName} ({$formattedQty} {$unit})");
                })->filter()->implode("\n");
                if ($materials) $description .= $description ? "\n\nRecipe Details:\n{$materials}" : "Recipe Details:\n{$materials}";
            }
            $unitPrice = $isTaxInclusive ? (float)($item->quantity > 0 ? ($subtotal / $item->quantity) : $item->rate) : (float)$item->rate;
            return [
                'no' => $idx + 1, 'name' => $item->mixDesign->design_name ?? 'N/A', 'description' => $description,
                'hsn' => $item->mixDesign->hsn_code ?? '-', 'qty' => (float)$item->quantity, 'received_qty' => 0,
                'unit' => $item->mixDesign->unit->unit_code ?? 'm³', 'unit_price' => $unitPrice,
                'tax_name' => $taxName ?: '-', 'tax_rate' => $taxRate, 'tax_group' => $taxGroup, 'tax_amount' => $priceTax,
                'total' => (float)($item->amount_total ?? ($item->quantity * $item->rate)),
            ];
        })->toArray();
        $taxLines = [];
        foreach ($customerPO->items as $item) {
            $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $priceTax = (float)$item->tax_amount; $subtotal = (float)($item->quantity * $item->rate);
            if ($priceTax <= 0 && !$taxModel) continue;
            if ($taxRate <= 0 && $subtotal > 0) { $taxRate = round(($priceTax / $subtotal) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST'; }
            if (empty($taxGroup)) $taxGroup = $isIntra ? 'GST' : 'IGST';
            $g = strtoupper(trim($taxGroup));
            if ($g === 'GST') { $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2); $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2); }
            else { $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax; }
        }
        $computedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
        $data['totals'] = [
            'sub_total' => (float)$customerPO->amount_untaxed, 'discount' => 0, 'tax_lines' => $computedTaxLines,
            'shipping' => 0, 'adjustment' => 0, 'round_off' => 0, 'grand_total' => (float)$customerPO->amount_total,
        ];
        $data['meta'] = [
            'currency_code' => 'INR', 'currency_symbol' => '₹', 'notes' => $customerPO->notes ?? '',
            'terms_text' => self::resolveTermsCondition($data['settings'], 'Customer PO', $customerPO->plant_id, ''),
            'total_words' => self::numberToWords($customerPO->amount_total, 'INR'),
        ];
        return $data;
    }

    public static function fromSalesOrder($salesOrder): array
    {
        $salesOrder->loadMissing(['customer','customer.addresses','site','plant','plant.entity','plant.addresses','mixDesign','mixDesign.concrete_grade','mixDesign.unit','mixDesign.items.product','mixDesign.items.uom','customerPO.items.mixDesign','customerPO.items.tax','customerPO.quotation.items.mixDesign','customerPO.quotation.items.tax','customerPO.quotation.items.mixDesign.items.product','customerPO.quotation.items.mixDesign.items.uom']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($salesOrder->plant_id, 'sales_orders') ?: self::getCustomSettings($salesOrder->plant_id, 'quotations');
        $data['doc_title']  = $data['settings']['pdf']['labels']['invoice_title'] ?? 'SALES ORDER';
        $data['doc_no']     = ($salesOrder->prefix ?? '') . ($salesOrder->order_no ?? $salesOrder->id);
        $data['doc_date']   = $salesOrder->created_at ? $salesOrder->created_at->format('d/m/Y') : now()->format('d/m/Y');
        $data['due_date']   = $salesOrder->scheduled_end ? \Carbon\Carbon::parse($salesOrder->scheduled_end)->format('d/m/Y') : '';
        $statusMap = [1 => 'SCHEDULED', 2 => 'IN PROGRESS', 3 => 'COMPLETED', 4 => 'CANCELLED'];
        $data['state'] = $statusMap[$salesOrder->status] ?? 'DRAFT';
        $plAddr = $salesOrder->plant->addresses->first();
        $data['company'] = [
            'name' => $salesOrder->plant->entity->entity_name ?? $salesOrder->plant->name, 'address' => $plAddr->line_1 ?? '',
            'city' => $plAddr->city ?? '', 'state' => $plAddr->state->state_name ?? $plAddr->state_code ?? '', 'pin' => $plAddr->zipcode ?? '',
            'gstin' => $salesOrder->plant->gstin ?? '', 'phone' => $salesOrder->plant->phone ?? '', 'email' => $salesOrder->plant->email ?? '',
            'seal_sign_path' => $salesOrder->plant->seal_sign_path ?? '',
        ];
        $custAddr = $salesOrder->customer?->addresses?->first();
        $data['bill_to'] = [
            'name' => $salesOrder->customer?->legal_name ?? 'N/A', 'address' => $custAddr?->line_1 ?? ($salesOrder->customer?->address_line1 ?? ''),
            'city' => $custAddr?->city ?? ($salesOrder->customer?->city ?? ''), 'state' => $custAddr?->state?->state_name ?? ($salesOrder->customer?->state ?? ''),
            'pin' => $custAddr?->zipcode ?? ($salesOrder->customer?->pincode ?? ''), 'gstin' => $salesOrder->customer?->gstin ?? '',
            'phone' => $salesOrder->customer?->mobile ?? $salesOrder->customer?->phone ?? '',
        ];
        $data['ship_to'] = [
            'name' => $salesOrder->site?->name ?? $data['bill_to']['name'], 'address' => $salesOrder->site?->site_address_1 ?? $data['bill_to']['address'],
            'city' => $salesOrder->site?->city ?? $data['bill_to']['city'], 'state' => $salesOrder->site?->state ?? $data['bill_to']['state'],
            'pin' => $salesOrder->site?->zipcode ?? $data['bill_to']['pin'],
        ];
        $isTaxInclusive = false; if ($salesOrder->customerPO) $isTaxInclusive = (bool)($salesOrder->customerPO->is_tax_inclusive ?? false);
        $plantGstin  = $salesOrder->plant->gstin ?? ''; $custGstin   = $salesOrder->customer?->gstin ?? '';
        $plantState  = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';
        $custState   = strlen($custGstin)  >= 2 ? substr($custGstin, 0, 2) : '';
        $isIntra     = !(strlen($custState) >= 2 && $custState !== $plantState);
        $quotationItems = $salesOrder->customerPO?->quotation?->items ?? collect();
        if ($quotationItems->isNotEmpty()) {
            $data['items'] = $quotationItems->map(function ($item, $idx) use ($isIntra, $isTaxInclusive) {
                $taxModel  = $item->tax; $taxRate   = $taxModel ? (float)$taxModel->tax_rate : 0.0;
                $taxGroup  = $taxModel ? ($taxModel->tax_group ?? '') : ''; $taxName   = $taxModel ? ($taxModel->tax_name ?? '') : '';
                $priceTax  = (float)$item->tax_amount; $subtotal  = (float)($item->untaxed_amount ?? ($item->quantity * $item->rate));
                if ($taxRate <= 0 && $priceTax > 0 && $subtotal > 0) { $taxRate = round(($priceTax / $subtotal) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST'; $taxName  = $taxGroup . ' ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%'; }
                $description = $item->description ?? $item->mixDesign?->design_code ?? '';
                if ($item->mixDesign && $item->mixDesign->items?->count() > 0) {
                    $materials = $item->mixDesign->items->map(function ($mdItem) {
                        $prodName = $mdItem->product?->title ?? 'Unknown'; $qty = (float)$mdItem->actual_quantity;
                        $unit = $mdItem->uom?->unit_code ?? ''; $formattedQty = $qty == floor($qty) ? (int)$qty : number_format($qty, 2);
                        return "• {$prodName} ({$formattedQty} {$unit})";
                    })->filter()->implode("\n");
                    if ($materials) $description .= $description ? "\n\nRecipe Details:\n{$materials}" : "Recipe Details:\n{$materials}";
                }
                $unitPrice = $isTaxInclusive ? (float)($item->quantity > 0 ? ($subtotal / $item->quantity) : $item->rate) : (float)$item->rate;
                return [
                    'no' => $idx + 1, 'name' => $item->mixDesign?->design_name ?? 'N/A', 'description' => $description,
                    'hsn' => $item->mixDesign?->hsn_code ?? '-', 'qty' => (float)$item->quantity, 'received_qty'=> (float)($salesOrder->produced_qty ?? 0),
                    'unit' => $item->mixDesign?->unit?->unit_code ?? 'm³', 'unit_price' => $unitPrice, 'tax_name' => $taxName ?: '-',
                    'tax_rate' => $taxRate, 'tax_group' => $taxGroup, 'tax_amount' => $priceTax, 'total' => (float)($item->amount_total ?? ($item->quantity * $item->rate)),
                ];
            })->toArray();
            $taxLines = [];
            foreach ($quotationItems as $item) {
                $taxModel = $item->tax; $taxRate  = $taxModel ? (float)$taxModel->tax_rate : 0.0;
                $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : ''; $priceTax = (float)$item->tax_amount; $subtotal = (float)($item->quantity * $item->rate);
                if ($priceTax <= 0 && !$taxModel) continue;
                if ($taxRate <= 0 && $subtotal > 0) { $taxRate  = round(($priceTax / $subtotal) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST'; }
                if (empty($taxGroup)) $taxGroup = $isIntra ? 'GST' : 'IGST';
                $g = strtoupper(trim($taxGroup));
                if ($g === 'GST') { $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2); $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2); }
                else { $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax; }
            }
            $computedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
            $grandTotal  = $quotationItems->sum('amount_total'); $untaxedAmt  = $quotationItems->sum('untaxed_amount') ?: $quotationItems->sum(fn($i) => $i->quantity * $i->rate);
            $data['totals'] = ['sub_total' => (float)$untaxedAmt, 'discount' => 0, 'tax_lines' => $computedTaxLines, 'shipping' => 0, 'adjustment' => 0, 'round_off' => 0, 'grand_total' => (float)$grandTotal];
        } else {
            $mixDesign = $salesOrder->mixDesign; $qty  = (float)($salesOrder->total_qty ?? 0);
            $poItem = null; if ($salesOrder->customerPO) $poItem = $salesOrder->customerPO->items->where('mix_design_id', $salesOrder->mix_design_id)->first();
            $rate = $poItem ? (float)$poItem->rate : (float)($mixDesign?->rate_per_qty ?? 0);
            $taxModel = $poItem?->tax; $taxRate  = 0.0; $taxGroup = ''; $taxName  = '-'; $priceTax = 0.0;
            if ($taxModel) { $taxRate  = (float)$taxModel->tax_rate; $taxGroup = $taxModel->tax_group ?? ''; $taxName  = $taxModel->tax_name ?? ''; }
            $subtotal = $qty * $rate;
            if ($isTaxInclusive) { $total = $subtotal; $untaxedAmt = $total / (1 + ($taxRate / 100)); $priceTax = $total - $untaxedAmt; $unitPrice = $qty > 0 ? ($untaxedAmt / $qty) : $rate; }
            else { $untaxedAmt = $subtotal; $priceTax = $untaxedAmt * ($taxRate / 100); $total = $untaxedAmt + $priceTax; $unitPrice = $rate; }
            if ($taxRate <= 0 && $poItem) {
                $itemTaxAmount = (float)$poItem->tax_amount; $itemUntaxedAmount = (float)($poItem->untaxed_amount ?? ($poItem->quantity * $poItem->rate));
                if ($itemTaxAmount > 0 && $itemUntaxedAmount > 0) {
                    $taxRate = round(($itemTaxAmount / $itemUntaxedAmount) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST';
                    $taxName  = $taxGroup . ' ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                    if ($isTaxInclusive) { $untaxedAmt = $total / (1 + ($taxRate / 100)); $priceTax = $total - $untaxedAmt; $unitPrice = $qty > 0 ? ($untaxedAmt / $qty) : $rate; }
                    else { $priceTax = $untaxedAmt * ($taxRate / 100); $total = $untaxedAmt + $priceTax; }
                }
            }
            $description = $mixDesign?->design_code ?? '';
            if ($mixDesign && $mixDesign->items?->count() > 0) {
                $materials = $mixDesign->items->map(function ($mdItem) {
                    $prodName = $mdItem->product?->title ?? 'Unknown'; $qtyVal = (float)$mdItem->actual_quantity;
                    $unit = $mdItem->uom?->unit_code ?? ''; $formattedQty = $qtyVal == floor($qtyVal) ? (int)$qtyVal : number_format($qtyVal, 2);
                    return "• {$prodName} ({$formattedQty} {$unit})";
                })->filter()->implode("\n");
                if ($materials) $description .= $description ? "\n\nRecipe Details:\n{$materials}" : "Recipe Details:\n{$materials}";
            }
            $data['items'] = $mixDesign ? [[
                'no' => 1, 'name' => $mixDesign->design_name ?? 'Concrete Mix', 'description' => $description,
                'hsn' => $mixDesign->hsn_code ?? '-', 'qty' => $qty, 'received_qty'=> (float)($salesOrder->produced_qty ?? 0),
                'unit' => $mixDesign->unit?->unit_code ?? 'm³', 'unit_price' => $unitPrice, 'tax_name' => $taxName ?: '-',
                'tax_rate' => $taxRate, 'tax_group' => $taxGroup, 'tax_amount' => $priceTax, 'total' => $total,
            ]] : [];
            $taxLines = [];
            if ($priceTax > 0 || $taxModel) {
                $g = strtoupper(trim($taxGroup ?: ($isIntra ? 'GST' : 'IGST')));
                if ($g === 'GST') { $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($priceTax / 2); $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($priceTax / 2); }
                else { $taxLines[$g] = ($taxLines[$g] ?? 0) + $priceTax; }
            }
            $computedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
            $data['totals'] = ['sub_total' => (float)$untaxedAmt, 'discount' => 0, 'tax_lines' => $computedTaxLines, 'shipping' => 0, 'adjustment' => 0, 'round_off' => 0, 'grand_total' => (float)$total];
        }
        $data['meta'] = [
            'currency_code' => 'INR', 'currency_symbol' => '₹', 'notes' => $salesOrder->terms_conditions ?? '',
            'terms_text' => self::resolveTermsCondition($data['settings'], 'Sales Order', $salesOrder->plant_id, ''),
            'total_words' => self::numberToWords($data['totals']['grand_total'], 'INR'), 'po_number' => '', 'project_name' => $salesOrder->site?->name ?? '',
        ];
        return $data;
    }

    // ─────────────────────────────────────────────────────
    //  NUMBER TO WORDS - INDIAN SYSTEM TILL CRORE ONLY
    //  Larger numbers => Hundred Crore / Thousand Crore / Lakh Crore
    // ─────────────────────────────────────────────────────
    private static array $ones = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    ];
    private static array $tens = [
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    ];

    // Converts 0 to 99,99,999 (no Crore word) - used for both rem and crore count
    private static function convertBelowCrore(int $num): string
    {
        if ($num === 0) return '';
        if ($num < 20) return self::$ones[$num];
        if ($num < 100) {
            $ten = (int)(floor($num / 10) * 10);
            $unit = $num % 10;
            return trim(self::$tens[$ten] . ($unit ? ' ' . self::$ones[$unit] : ''));
        }
        if ($num < 1000) {
            $h = intdiv($num, 100);
            $rem = $num % 100;
            return trim(self::$ones[$h] . ' Hundred' . ($rem ? ' ' . self::convertBelowCrore($rem) : ''));
        }
        if ($num < 100000) { // Thousand : 1,000 - 99,999
            $th = intdiv($num, 1000);
            $rem = $num % 1000;
            return trim(self::convertBelowCrore($th) . ' Thousand' . ($rem ? ' ' . self::convertBelowCrore($rem) : ''));
        }
        // Lakh : 1,00,000 - 99,99,999
        $lakh = intdiv($num, 100000);
        $rem = $num % 100000;
        return trim(self::convertBelowCrore($lakh) . ' Lakh' . ($rem ? ' ' . self::convertBelowCrore($rem) : ''));
    }

    // Main Indian converter - max unit is Crore, bigger = Thousand/Lakh Crore
    private static function convertIndianTillCrore(int $num): string
    {
        if ($num === 0) return '';
        if ($num < 10000000) {
            return self::convertBelowCrore($num);
        }
        $croreCount = intdiv($num, 10000000);
        $rem = $num % 10000000;

        // croreCount itself may be > 1 Lakh, convert it without Crore word to get Thousand/Lakh Crore
        $croreWords = self::convertBelowCrore($croreCount);
        // If croreCount >= 1 Crore (i.e. 100 Lakh), convertBelowCrore will give "One Hundred Lakh"
        // To avoid "Lakh Lakh" infinite, handle very large croreCount recursively via same logic
        if ($croreCount >= 10000000) {
            // For 1 Crore Crore and above, express as Lakh Crore correctly
            $croreWords = self::convertIndianTillCrore($croreCount);
            // Remove trailing " Crore" from croreWords and keep it as multiplier
            $croreWords = preg_replace('/\s+Crore.*$/', '', $croreWords);
        }

        $result = trim($croreWords . ' Crore');
        if ($rem > 0) {
            $result .= ' ' . self::convertBelowCrore($rem);
        }
        return trim($result);
    }

    private static function convertNumberToWords($no): string
    {
        $no = (int) floor(abs((float) $no));
        if ($no === 0) return '';
        return self::convertIndianTillCrore($no);
    }

    public static function numberToWords($number, $currency = 'INR'): string
    {
        $number = (float) $number;
        $isNegative = $number < 0;
        $number = abs($number);
        $no = (int) floor($number);
        $point = (int) round(($number - $no) * 100);
        if ($point === 100) { $no += 1; $point = 0; }

        $integerWords = $no === 0 ? 'Zero' : self::convertIndianTillCrore($no);
        $currencyLabel = $currency === 'INR' ? 'Rupees' : $currency;
        $result = $currencyLabel . ' ' . $integerWords;
        if ($point > 0) {
            $result .= ' and ' . self::convertBelowCrore($point) . ' Paise';
        }
        $result .= ' Only';
        return $isNegative ? 'Minus ' . $result : $result;
    }

    public static function resolveTemplateKey(string $moduleKey, int $plantId): string
    {
        $setting = PrintTemplateSetting::where('module_key', $moduleKey)->where('plant_id', $plantId)->with('template')->first();
        return $setting?->template?->key ?? 'standard';
    }

    public static function supportedTemplates(): array
    {
        return ['standard','elite','modern','spreadsheet','tallysheet','compact','indian_gst','formal_gst','standard_indigo','minimalist_lite','delivery_challan_a4'];
    }

    public static function resolveView(string $templateKey): string
    {
        if ($templateKey === 'delivery_challan_a4') return "pdfs.batches.delivery_token";
        $map = ['formal_gst' => 'indian_gst','standard_indigo' => 'elite','minimalist_lite' => 'compact'];
        $supported = self::supportedTemplates();
        $key = in_array($templateKey, $supported) ? ($map[$templateKey] ?? $templateKey) : 'standard';
        return "pdfs.templates.{$key}";
    }

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
            case 'invoices': case 'gst_invoices': $invoiceTitle = 'TAX INVOICE'; break;
            case 'purchase_orders': $invoiceTitle = 'PURCHASE ORDER'; break;
            case 'purchase_bills': $invoiceTitle = 'PURCHASE BILL'; break;
            case 'quotations': $invoiceTitle = 'QUOTATION'; break;
            case 'customer_pos': $invoiceTitle = 'CUSTOMER PO'; break;
            case 'sales_orders': $invoiceTitle = 'SALES ORDER'; break;
            case 'delivery_challans': $invoiceTitle = 'DELIVERY CHALLAN'; break;
            case 'delivery_notes': $invoiceTitle = 'DELIVERY NOTE'; break;
            case 'credit_notes': $invoiceTitle = 'CREDIT NOTE'; break;
            case 'statements': $invoiceTitle = 'STATEMENT OF ACCOUNT'; break;
        }
        return [
            'pdf' => [
                'company_name'=>true,'logo'=>true,'address'=>true,'phone'=>true,'email'=>true,'gstin'=>true,
                'invoice_title'=>true,'invoice_number'=>true,'date'=>true,'due_date'=>true,'status'=>false,
                'bill_to'=>true,'ship_to'=>true,'hsn_code'=>true,'description'=>true,'unit'=>true,'discount'=>true,
                'tax_percent'=>true,'cgst'=>true,'sgst'=>true,'igst'=>true,'shipping'=>true,'adjustment'=>true,
                'round_off'=>true,'total_words'=>true,'notes'=>true,'terms'=>true,'signature'=>true,
                'labels' => ['invoice_title'=>$invoiceTitle,'bill_to'=>'Bill To','ship_to'=>'Ship To','rate'=>'Rate','amount'=>'Amount']
            ],
            'excel' => ['hsn_code'=>true,'discount'=>true]
        ];
    }

    public static function fromDeliveryChallan($batch): array
    {
        $batch->loadMissing(['salesOrder','salesOrder.customer','salesOrder.site','salesOrder.plant','salesOrder.plant.entity','salesOrder.plant.addresses','salesOrder.mixDesign','salesOrder.mixDesign.concrete_grade','salesOrder.mixDesign.unit','dispatches','dispatches.truck','dispatches.driver','dispatches.transport','dispatches.loadTax','materials.product','materials.uom','operator']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($batch->salesOrder->plant_id, 'delivery_challans');
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'DELIVERY CHALLAN';
        $data['doc_no']    = 'B' . ($batch->batch_no ?? $batch->id);
        $data['doc_date']  = optional($batch->load_time ?? $batch->created_at)->format('d/m/Y H:i');
        $dispatch = $batch->dispatches->first();
        $data['delivery_date'] = $dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('d/m/Y H:i') : ($batch->load_time ? $batch->load_time->format('d/m/Y H:i') : 'N/A');
        $data['state']     = $batch->status_text ?? 'DISPATCHED';
        $plant = $batch->salesOrder->plant; $plAddr = $plant->addresses->first();
        $data['company'] = ['name'=>$plant->name,'address'=>$plAddr->line_1 ?? '','city'=>$plAddr->city ?? '','state'=>$plAddr->state->state_name ?? $plAddr->state_code ?? '','pin'=>$plAddr->zipcode ?? '','gstin'=>$plant->gstin ?? '','phone'=>$plant->phone ?? '','email'=>$plant->email ?? ''];
        $customer = $batch->salesOrder->customer;
        $data['bill_to'] = ['name'=>$customer->legal_name ?? $customer->name ?? 'N/A','address'=>$customer->address_line1 ?? '','city'=>$customer->city ?? '','state'=>$customer->state ?? '','pin'=>$customer->pincode ?? '','gstin'=>$customer->gstin ?? '','phone'=>$customer->phone ?? ''];
        $site = $batch->salesOrder->site;
        $data['ship_to'] = ['name'=>$site->name ?? $data['bill_to']['name'],'address'=>$site->site_address_1 ?? $data['bill_to']['address'],'city'=>$site->city ?? $data['bill_to']['city'],'state'=>$site->state ?? $data['bill_to']['state'],'pin'=>$site->zipcode ?? $data['bill_to']['pin']];
        $groupedMaterials = $batch->materials->groupBy(function($mat){ return $mat->product_id ?? $mat->material_name; })->map(function($group){
            $first = $group->first(); $target = $group->sum('target_qty'); $actual = $group->sum('actual_qty');
            return (object)['material_name'=>$first->material_name ?: ($first->product->title ?? 'Material'),'uom_code'=>$first->uom->unit_code ?? 'kg','hsn_code'=>$first->product->hsn_code ?? '-','target_qty'=>$target,'actual_qty'=>$actual,'deviation_quantity'=>$group->sum('deviation_quantity')];
        });
        $itemsList = [];
        if ($dispatch) {
            $mixDesign = $batch->salesOrder->mixDesign; $mixDesignName = $mixDesign?->design_name ?? ($mixDesign?->concrete_grade?->name ?? 'Concrete Mix');
            $qty = (float)($dispatch->delivered_qty ?: $batch->batch_size); $rate = (float)($dispatch->load_rate ?? 0);
            $subTotal = (float)($dispatch->load_untax_amount ?? ($qty * $rate)); $taxAmount = (float)($dispatch->load_tax_amount ?? 0);
            $totalAmount = (float)($dispatch->load_total_amount ?? ($subTotal + $taxAmount)); $taxRate = $dispatch->loadTax?->rate ?? 0; $taxName = $dispatch->loadTax?->name ?? '-';
            $itemsList[] = ['no'=>1,'name'=>$mixDesignName,'description'=>"Concrete Mix Design - " . ($mixDesign?->design_code ?? ''),'hsn'=>'3824','qty'=>$qty,'received_qty'=>$qty,'unit'=>$dispatch->uom?->unit_code ?? 'CBM','unit_price'=>$rate,'tax_name'=>$taxName,'tax_rate'=>$taxRate,'tax_amount'=>$taxAmount,'total'=>$totalAmount];
        }
        $sno = count($itemsList) + 1;
        foreach ($groupedMaterials->values() as $item) {
            $target = (float) $item->target_qty; $actual = (float) $item->actual_qty; $deviationVal = $actual - $target; $devPercent = 0;
            if ($target > 0) $devPercent = ($deviationVal / $target) * 100;
            $devSign = $devPercent > 0 ? '+' : '';
            $desc = sprintf("Target: %s %s | Actual: %s %s | Dev: %s%s%%", number_format($target, 2), $item->uom_code, number_format($actual, 2), $item->uom_code, $devSign, number_format($devPercent, 2));
            $itemsList[] = ['no'=>$sno++,'name'=>$item->material_name,'description'=>$desc,'hsn'=>$item->hsn_code,'qty'=>$actual ?: $target,'received_qty'=>$actual,'unit'=>$item->uom_code,'unit_price'=>0.00,'tax_name'=>'-','tax_rate'=>0.00,'tax_amount'=>0.00,'total'=>0.00];
        }
        $data['items'] = $itemsList;
        $taxLines = [];
        if ($dispatch && $dispatch->load_tax_amount > 0) $taxLines[] = ['name'=>$dispatch->loadTax?->name ?? 'GST','rate'=>$dispatch->loadTax?->rate ?? 18,'amount'=>(float)$dispatch->load_tax_amount];
        $data['totals'] = ['sub_total'=>(float)($dispatch?->load_untax_amount ?? 0),'discount'=>(float)($dispatch?->discount_amount ?? 0),'tax_lines'=>$taxLines,'shipping'=>(float)($dispatch?->transport_expenses ?? 0),'adjustment'=>(float)($dispatch?->adjustment_amount ?? 0),'round_off'=>(float)($dispatch?->round_off ?? 0),'grand_total'=>(float)($dispatch?->load_total_amount ?? 0)];
        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');
        $isMetricTon = !empty($settings['InvoiceInMetricTon']) && $settings['InvoiceInMetricTon'] == 1;
        $emptyWeight = (float) ($dispatch?->empty_weight_truck ?? 0); $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
        $netWeight = (float) ($dispatch?->net_weight ?? ($loadedWeight - $emptyWeight));
        $unitLabel = $isMetricTon ? ' MT' : ' kg'; $decimals = $isMetricTon ? 3 : 0;
        $emptyWeightStr = number_format($emptyWeight, $decimals) . $unitLabel; $loadedWeightStr = number_format($loadedWeight, $decimals) . $unitLabel; $netWeightStr = number_format($netWeight, $decimals) . $unitLabel;
        $weightNotes = "VEHICLE WEIGHT DETAILS:\n" . "Truck No: " . ($dispatch?->truck?->registration ?? '-') . "\n" . "Driver: " . (trim(($dispatch?->driver?->first_name ?? '') . ' ' . ($dispatch?->driver?->last_name ?? '')) ?: '-') . "\n" . "Empty Weight: " . $emptyWeightStr . " (" . ($dispatch?->empty_time ? \Carbon\Carbon::parse($dispatch->empty_time)->format('d-m-Y H:i') : '-') . ")\n" . "Loaded Weight: " . $loadedWeightStr . " (" . ($dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('d-m-Y H:i') : '-') . ")\n" . "Net Weight: " . $netWeightStr . "\n\n" . "Batch size: " . number_format((float) $batch->batch_size, 2) . " m³\n" . "Concrete Grade: " . ($batch->salesOrder?->mixDesign?->concrete_grade?->name ?? ($batch->salesOrder?->mixDesign?->design_name ?? '-')) . " / Recipe Code: " . ($batch->salesOrder?->mixDesign?->design_code ?? '-');
        $data['meta'] = [
            'currency_code'=>'INR','currency_symbol'=>'₹','notes'=>$weightNotes,
            'terms_text'=>self::resolveTermsCondition($data['settings'], 'Delivery Challan', $batch->salesOrder->plant_id, $batch->salesOrder?->terms_conditions ?? "1. Goods received in good condition.\n2. Any variation in quantity to be reported immediately."),
            'total_words'=>'','po_number'=>$batch->salesOrder?->order_no ?? '-','project_name'=>'Concrete Grade: ' . ($batch->salesOrder?->mixDesign?->concrete_grade?->name ?? '-'),
        ];
        $data['batch'] = $batch;
        return $data;
    }
}