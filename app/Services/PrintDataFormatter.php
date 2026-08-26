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

    public static function dummy(string $category = 'invoice', ?array $customSettings = null): array
    {
        $plantId = session('active_plant_id') ?: 1;

        // 1. Try to load real latest record from DB based on module category
        try {
            switch ($category) {
                case 'invoices':
                case 'gst_invoices':
                case 'billings':
                case 'purchase_bills':
                    $type = ($category === 'purchase_bills') ? 'bill' : 'invoice';
                    $invoice = \App\Models\Invoice::where('plant_id', $plantId)
                        ->where('invoice_type', $type)
                        ->latest()
                        ->first()
                        ?? \App\Models\Invoice::latest()->first();
                    if ($invoice) {
                        return self::fromInvoice($invoice);
                    }
                    break;

                case 'purchase_orders':
                    $po = \App\Models\PurchaseOrder::where('plant_id', $plantId)->latest()->first()
                        ?? \App\Models\PurchaseOrder::latest()->first();
                    if ($po) {
                        return self::fromPurchaseOrder($po);
                    }
                    break;

                case 'quotations':
                    $quotation = \App\Models\Quotation::where('plant_id', $plantId)->latest()->first()
                        ?? \App\Models\Quotation::latest()->first();
                    if ($quotation) {
                        return self::fromQuotation($quotation, $customSettings);
                    }
                    break;

                case 'customer_pos':
                    $cpo = \App\Models\CustomerPO::where('plant_id', $plantId)->latest()->first()
                        ?? \App\Models\CustomerPO::latest()->first();
                    if ($cpo) {
                        return self::fromCustomerPO($cpo, $customSettings);
                    }
                    break;

                case 'sales_orders':
                    $so = \App\Models\SalesOrder::where('plant_id', $plantId)->latest()->first()
                        ?? \App\Models\SalesOrder::latest()->first();
                    if ($so) {
                        if (empty($so->pump_rate) || (float)$so->pump_rate <= 0) {
                            $so->pump_rate = 1500.00;
                        }
                        return self::fromSalesOrder($so);
                    }
                    break;

                case 'delivery_challans':
                    $batch = \App\Models\Batch::whereHas('workOrder', fn ($q) => $q->where('plant_id', $plantId))->latest()->first()
                        ?? \App\Models\Batch::latest()->first();
                    if ($batch) {
                        return self::fromDeliveryChallan($batch);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // Fallback to model-based sample query if direct model load fails
        }

        // 2. If no record for that category exists, build dynamic payload from real DB models (Plant, Partner, Product)
        $data = self::base();
        $data['settings']  = self::getCustomSettings($plantId, $category);
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? (strtoupper($category) . ' DOCUMENT');
        $data['doc_no']    = 'REF-' . now()->format('Y') . '-001';
        $data['doc_date']  = now()->format('d/m/Y');
        $data['due_date']  = now()->addDays(15)->format('d/m/Y');
        $data['delivery_date'] = now()->addDays(5)->format('d/m/Y');

        // Real Plant company details
        $plant = \App\Models\Plant::with(['entity', 'addresses'])->find($plantId)
            ?? \App\Models\Plant::with(['entity', 'addresses'])->first();
        if ($plant) {
            $data['company'] = self::formatCompany($plant);
        } else {
            $data['company'] = [
                'name'    => 'ModoMines Tech Solutions', 'address' => '123 Cloud Avenue, Tech Park', 'city'    => 'Chennai',
                'state'   => 'Tamil Nadu', 'pin'     => '600001', 'gstin'   => '33AAAAA0000A1Z5', 'phone'   => '+91 98765 43210', 'email'   => 'support@modomines.com',
            ];
        }

        // Real Patron details from DB
        $partner = \App\Models\Patron::where('plant_id', $plantId)->first()
            ?? \App\Models\Patron::first();
        if ($partner) {
            $data['bill_to'] = self::formatPartner($partner);
            $data['ship_to'] = [
                'name'    => $data['bill_to']['name'] . ' - Site A',
                'address' => $data['bill_to']['address'],
                'city'    => $data['bill_to']['city'],
                'state'   => $data['bill_to']['state'],
                'pin'     => $data['bill_to']['pin'],
            ];
        } else {
            $data['bill_to'] = [
                'name'    => 'Alpha Prime Industries', 'address' => '45 Industrial Estate, Phase II', 'city'    => 'Coimbatore',
                'state'   => 'Tamil Nadu', 'pin'     => '641001', 'gstin'   => '33BBBBB1111B1Z2', 'phone'   => '+91 422 2345678',
            ];
            $data['ship_to'] = [
                'name'    => 'Alpha Prime - Site A', 'address' => 'Plot 88, Near New Bypass', 'city'    => 'Salem', 'state'   => 'Tamil Nadu', 'pin'     => '636001',
            ];
        }

        // Real Products from DB if available
        $products = \App\Models\Product::with(['unit', 'saleTax'])->where('status', 'active')->limit(2)->get();
        if ($products->count() > 0) {
            $items = [];
            $subtotal = 0;
            $totalTax = 0;

            foreach ($products as $idx => $prod) {
                $qty = ($idx === 0) ? 25.0 : 2.0;
                $price = (float)($prod->sales_price ?? $prod->purchase_price ?? 4500.00);
                if ($price <= 0) $price = 4500.00;
                
                $lineTotal = $qty * $price;
                $taxRate = $prod->saleTax ? (float)$prod->saleTax->tax_rate : 12.0;
                $taxAmount = ($lineTotal * $taxRate) / 100;
                
                $subtotal += $lineTotal;
                $totalTax += $taxAmount;

                $items[] = [
                    'no' => $idx + 1,
                    'name' => $prod->title ?? $prod->name ?? 'Product ' . ($idx + 1),
                    'description' => $prod->description ?? $prod->title ?? '',
                    'hsn' => $prod->hsn_code ?? '382450',
                    'qty' => $qty,
                    'received_qty' => $qty,
                    'unit' => $prod->unit?->unit_code ?? $prod->uom?->unit_code ?? 'm³',
                    'unit_price' => $price,
                    'tax_name' => 'GST ' . (int)$taxRate . '%',
                    'tax_rate' => $taxRate,
                    'tax_group' => 'GST',
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal + $taxAmount,
                ];
            }

            $data['items'] = $items;
            $data['totals'] = [
                'sub_total'   => $subtotal,
                'discount'    => 0,
                'tax_lines'   => [
                    ['label' => 'CGST', 'amount' => $totalTax / 2],
                    ['label' => 'SGST', 'amount' => $totalTax / 2],
                ],
                'shipping'    => 0,
                'adjustment'  => 0,
                'round_off'   => 0,
                'grand_total' => $subtotal + $totalTax,
            ];
            $data['meta']['total_words'] = self::numberToWords($subtotal + $totalTax, 'INR');
        } else {
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
            $data['meta']['total_words'] = 'Rupees Four Lakh Five Thousand Nine Hundred Only';
        }

        $data['meta']['project_name']   = $plant?->name ?? 'Grand Mall Construction - Phase 1';
        $data['meta']['po_number']     = 'PO-8877';
        $data['meta']['terms_text']    = self::resolveTermsCondition($data['settings'], 'Invoice', $plantId, "1. Payment within 15 days of delivery.\n2. Goods once sold will not be taken back.");
        $data['meta']['sales_executive_name']   = 'Sales Executive';
        $data['meta']['sales_executive_mobile'] = $plant?->phone ?? '';

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
        if (!empty($fallbackTerms)) return $fallbackTerms;
        return '';
    }

    public static function resolveBankDetails($plant, $entity = null): array
    {
        // 1. Direct plant bank details if configured
        if (!empty($plant?->bank_name)) {
            return [
                'account_name'   => $plant?->bank_account_name ?? ($plant?->name ?? ''),
                'account_number' => $plant?->bank_account_number ?? '',
                'bank_name'      => $plant?->bank_name ?? '',
                'branch'         => $plant?->bank_branch ?? '',
                'ifsc_code'      => $plant?->ifsc_code ?? '',
            ];
        }

        // 2. Entity primary bank account fallback
        $legalEntity = $entity ?? $plant?->entity;
        $primaryBank = $legalEntity?->bankAccounts?->firstWhere('is_primary', 1) 
            ?? $legalEntity?->bankAccounts?->first();

        if ($primaryBank) {
            return [
                'account_name'   => $primaryBank->account_name ?? ($legalEntity?->legal_name ?? $legalEntity?->entity_name ?? ''),
                'account_number' => $primaryBank->account_number ?? '',
                'bank_name'      => $primaryBank->bank_name ?? '',
                'branch'         => $primaryBank->bank_branch ?? '',
                'ifsc_code'      => $primaryBank->ifsc_code ?? '',
            ];
        }

        return [
            'account_name'   => $plant?->bank_account_name ?? ($plant?->name ?? ''),
            'account_number' => $plant?->bank_account_number ?? '',
            'bank_name'      => $plant?->bank_name ?? '',
            'branch'         => $plant?->bank_branch ?? '',
            'ifsc_code'      => $plant?->ifsc_code ?? '',
        ];
    }

    public static function formatCompany($plant): array
    {
        $plAddr = $plant?->addresses?->first();
        $stateName = $plAddr?->state?->state_name ?? $plAddr?->state_code ?? '';
        $stateCode = $plAddr?->state?->state_code ?? ($plant?->gstin && strlen($plant->gstin) >= 2 ? substr($plant->gstin, 0, 2) : '');
        $gstin = $plant?->gstin ?? '';
        $pan = strlen($gstin) >= 14 ? substr($gstin, 2, 10) : ($plant?->pan ?? '');

        return [
            'name'           => $plant?->entity?->legal_name ?? $plant?->entity?->entity_name ?? $plant?->name ?? 'Company',
            'address'        => $plAddr?->line_1 ?? '',
            'city'           => $plAddr?->city ?? '',
            'state'          => $stateName,
            'state_code'     => $stateCode,
            'pin'            => $plAddr?->zipcode ?? '',
            'gstin'          => $gstin,
            'pan'            => $pan,
            'msme_no'        => $plant?->msme_no ?? $plant?->udyam_no ?? '',
            'phone'          => $plant?->phone ?? $plant?->mobile_number ?? '',
            'email'          => $plant?->email ?? $plant?->email_address ?? '',
            'logo_path'      => $plant?->logo_path ?? '',
            'seal_sign_path' => $plant?->seal_sign_path ?? '',
            'upi_qr_path'    => $plant?->upi_qr_path ?? '',
            'bank'           => self::resolveBankDetails($plant),
        ];
    }

    public static function formatPartner($partner): array
    {
        if (!$partner) {
            return [
                'name' => 'N/A', 'address' => '', 'city' => '', 'state' => '', 'pin' => '', 'gstin' => '', 'phone' => '',
            ];
        }

        $partnerAddr = null;
        $primaryContact = null;

        if (isset($partner->contacts)) {
            $primaryContact = $partner->contacts->where('is_primary', true)->first() ?? $partner->contacts->first();
            if ($primaryContact && isset($primaryContact->addresses)) {
                $partnerAddr = $primaryContact->addresses->where('is_primary', true)->first() ?? $primaryContact->addresses->first();
            }
        }

        if (!$partnerAddr && isset($partner->addresses)) {
            $partnerAddr = $partner->addresses->first();
        }

        $name = $partner->legal_name ?: ($partner->name ?: 'N/A');
        $address = $partnerAddr?->line_1 ?: ($partner->address_line1 ?? '');
        $city = $partnerAddr?->city ?: ($partner->city ?? '');
        
        $stateVal = '';
        if ($partnerAddr) {
            $stateVal = $partnerAddr->state?->state_name ?: ($partnerAddr->state_code ?? '');
        }
        if (empty($stateVal)) {
            $stateVal = $partner->state ?? '';
        }

        $pin = $partnerAddr?->zipcode ?: ($partner->pincode ?? '');
        $gstin = $partner->gstin ?? '';
        
        $phone = $partner->phone ?: ($partner->mobile ?? '');
        if (empty($phone) && $primaryContact) {
            $phone = $primaryContact->mobile ?? '';
        }

        return [
            'name'    => $name,
            'address' => $address,
            'city'    => $city,
            'state'   => $stateVal,
            'pin'     => $pin,
            'gstin'   => $gstin,
            'phone'   => $phone,
        ];
    }

    public static function formatShipTo($site, array $billTo): array
    {
        return [
            'name'    => $site?->name ?: $billTo['name'],
            'address' => $site?->site_address_1 ?: ($site?->address ?: $billTo['address']),
            'city'    => $site?->city ?: $billTo['city'],
            'state'   => $site?->state ?: $billTo['state'],
            'pin'     => $site?->zipcode ?: ($site?->pincode ?: $billTo['pin']),
        ];
    }

    public static function isIntraState(?string $plantGstin, ?string $partnerGstin): bool
    {
        $plantState = strlen($plantGstin ?? '') >= 2 ? substr($plantGstin, 0, 2) : '33';
        $partnerState = strlen($partnerGstin ?? '') >= 2 ? substr($partnerGstin, 0, 2) : '';
        return !(strlen($partnerState) >= 2 && $partnerState !== $plantState);
    }

    public static function resolveTaxDetails($taxModel, bool $isIntra, float $priceTax, float $subtotal): array
    {
        $taxRate = $taxModel ? (float)$taxModel->tax_rate : 0.0;
        $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
        $taxName = $taxModel ? ($taxModel->tax_name ?? '') : '';

        if ($taxRate <= 0 && $priceTax > 0 && $subtotal > 0) {
            $taxRate = round(($priceTax / $subtotal) * 100, 2);
            $taxGroup = $isIntra ? 'GST' : 'IGST';
            $taxName = $taxGroup . ' ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
        }

        return [
            'rate' => $taxRate,
            'group' => $taxGroup,
            'name' => $taxName,
        ];
    }

    public static function compileTaxLines($items, bool $isIntra, string $taxField, $subtotalFieldOrFn): array
    {
        $taxLines = [];
        foreach ($items as $item) {
            $taxModel = $item->tax;
            $taxRate = $taxModel ? (float)$taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $priceTax = (float)($item->{$taxField} ?? 0);
            
            $subtotal = 0.0;
            if (is_callable($subtotalFieldOrFn)) {
                $subtotal = (float)$subtotalFieldOrFn($item);
            } else {
                $subtotal = (float)($item->{$subtotalFieldOrFn} ?? 0);
            }

            if ($priceTax <= 0 && !$taxModel) {
                continue;
            }

            if ($taxRate <= 0 && $subtotal > 0) {
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

        return collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
    }

    public static function resolveMixDesignName($mixDesign): string
    {
        if (!$mixDesign) {
            return 'Concrete Mix';
        }
        return $mixDesign->concrete_grade?->name 
            ?? $mixDesign->concreteGrade?->name 
            ?? $mixDesign->design_type 
            ?? $mixDesign->design_name 
            ?? '-';
    }

    public static function formatMixDesignDescription(?string $baseDesc, $mixDesign): string
    {
        $description = $baseDesc ?? '';
        if ($mixDesign && $mixDesign->items && $mixDesign->items->count() > 0) {
            $materials = $mixDesign->items->map(function ($mdItem) {
                $prodName = $mdItem->product->title ?? $mdItem->product?->title ?? 'Unknown';
                $qty = (float)$mdItem->actual_quantity;
                $unit = $mdItem->uom->unit_code ?? $mdItem->uom?->unit_code ?? '';
                $formattedQty = $qty == floor($qty) ? (int)$qty : number_format($qty, 2);
                return trim("• {$prodName} ({$formattedQty} {$unit})");
            })->filter()->implode("\n");
            
            if ($materials) {
                $description .= $description ? "\n\nRecipe Details:\n{$materials}" : "Recipe Details:\n{$materials}";
            }
        }
        return $description;
    }

    public static function resolveRecipeMaterials($mixDesign): array
    {
        $materials = [];
        if ($mixDesign && $mixDesign->items && $mixDesign->items->count() > 0) {
            foreach ($mixDesign->items as $mdItem) {
                $prodName = $mdItem->product->title ?? $mdItem->product?->title ?? $mdItem->product?->name ?? 'Material';
                $qty = (float)$mdItem->actual_quantity;
                $unit = $mdItem->uom->unit_code ?? $mdItem->uom?->unit_code ?? '';
                $formattedQty = ($qty == floor($qty)) ? (int)$qty : number_format($qty, 2);
                $materials[] = [
                    'name' => $prodName,
                    'qty'  => $formattedQty,
                    'uom'  => $unit,
                ];
            }
        }
        return $materials;
    }

    // ─────────────────────────────────────────────────────
    //  FORMATTERS
    // ─────────────────────────────────────────────────────
    public static function fromPurchaseOrder($order): array
    {
        $order->loadMissing(['items.product', 'items.uom', 'items.tax', 'vendor', 'plant', 'plant.entity', 'plant.addresses', 'currency']);
        $data = self::base();
        $templateKey = self::resolveTemplateKey('purchase_orders', $order->plant_id);
        $data['settings'] = self::getCustomSettings($order->plant_id, 'purchase_orders', $templateKey);
        $data['doc_title']     = $data['settings']['pdf']['labels']['invoice_title'] ?? 'PURCHASE ORDER';
        $data['doc_no']        = $order->ref_no;
        $data['doc_date']      = $order->date_order?->format('d/m/Y') ?? 'N/A';
        $data['due_date']      = $order->due_date?->format('d/m/Y') ?? 'N/A';
        $data['delivery_date'] = $order->date_planned?->format('d/m/Y') ?? 'N/A';
        $data['state']         = strtoupper($order->state ?? 'DRAFT');
        
        $data['company'] = self::formatCompany($order->plant);
        $data['bill_to'] = self::formatPartner($order->vendor);
        $data['ship_to'] = [
            'name'    => $data['company']['name'],
            'address' => $data['company']['address'],
            'city'    => $data['company']['city'],
            'state'   => $data['company']['state'],
            'pin'     => $data['company']['pin'],
        ];

        $isIntra = self::isIntraState($order->plant->gstin ?? '', $order->vendor->gstin ?? '');
        $data['items'] = $order->items->map(function ($item, $idx) use ($isIntra) {
            $taxDetails = self::resolveTaxDetails($item->tax, $isIntra, (float)$item->price_tax, (float)$item->price_subtotal);
            return [
                'no' => $idx + 1, 'name' => $item->product->title ?? '', 'description' => $item->description ?? '',
                'hsn' => $item->product->hsn_code ?? '-', 'qty' => (float)$item->product_quantity,
                'received_qty' => (float)($item->received_quantity ?? 0), 'unit' => $item->uom->unit_code ?? '',
                'unit_price' => (float)$item->unit_price, 'tax_name' => $taxDetails['name'] ?: '-', 'tax_rate' => $taxDetails['rate'],
                'tax_group' => $taxDetails['group'], 'tax_amount' => (float)$item->price_tax, 'total' => (float)$item->price_total,
            ];
        })->toArray();

        $data['totals'] = [
            'sub_total'   => (float)$order->amount_untaxed, 'discount'    => (float)($order->discount_amount ?? 0),
            'tax_lines'   => self::compileTaxLines($order->items, $isIntra, 'price_tax', 'price_subtotal'),
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

    public static function fromInvoice($invoice): array
    {
        $invoice->loadMissing(['plant', 'plant.entity', 'plant.addresses', 'partner', 'partner.addresses', 'partner.contacts.addresses', 'items.tax', 'items.uom', 'items.itemTaxes', 'orderTaxes']);
        $data = self::base();
        $data['id'] = $invoice->id;
        $data['invoice_id'] = $invoice->id;
        $data['invoice'] = $invoice;
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
        $data['company']   = self::formatCompany($invoice->plant);
        
        $partner = $invoice->partner;
        $dispatch = \App\Models\Dispatch::whereHas('status', function ($q) use ($invoice) {
            $q->where('invoice_id', $invoice->id);
        })->with([
            'salesOrder.customer', 'salesOrder.site', 'salesOrder.salesExecutive', 'salesOrder.mixDesign',
            'unloadSite', 'customer', 'customerPO.patron', 'customerPO.site',
            'concretePump', 'truck', 'transport', 'driver', 'mixDesign', 'salesExecutive'
        ])->first();

        if (!$dispatch && !empty($invoice->ref_id)) {
            $refIds = explode(',', $invoice->ref_id);
            $firstRefId = trim($refIds[0] ?? '');
            if (is_numeric($firstRefId)) {
                $dispatch = \App\Models\Dispatch::with([
                    'salesOrder.customer', 'salesOrder.site', 'salesOrder.salesExecutive', 'salesOrder.mixDesign',
                    'unloadSite', 'customer', 'customerPO.patron', 'customerPO.site',
                    'concretePump', 'truck', 'transport', 'driver', 'mixDesign', 'salesExecutive'
                ])->find($firstRefId);
            }
        }

        if ($dispatch) {
            if (!$partner || empty($partner->name)) {
                $partner = $dispatch->customer ?: ($dispatch->salesOrder?->customer ?: ($dispatch->customerPO?->patron ?: null));
            }
        }

        // Resolve relations linked directly via Dispatch / SalesOrder
        $salesOrder = $dispatch?->salesOrder;
        $customerPO = $dispatch?->customerPO ?: $salesOrder?->customerPO;

        if ($partner) {
            $partner->loadMissing(['addresses', 'contacts.addresses']);
        }
        
        $data['bill_to'] = self::formatPartner($partner);

        $site = null;
        if ($dispatch) {
            $site = $dispatch->unloadSite ?: ($dispatch->salesOrder?->site ?: ($dispatch->customerPO?->site ?: null));
        }
        if (!$site && $customerPO) {
            $site = $customerPO->site;
        }
        if (!$site && $salesOrder) {
            $site = $salesOrder->site;
        }

        $data['ship_to'] = self::formatShipTo($site, $data['bill_to']);

        // Resolve sales person
        $salesExec = $dispatch?->salesExecutive ?: ($salesOrder?->salesExecutive ?: null);
        $salesPersonName = '';
        if ($salesExec) {
            $salesPersonName = trim(($salesExec->first_name ?? '') . ' ' . ($salesExec->last_name ?? ''));
        }

        // Resolve pump type / registration
        $pumpName = '-';
        if ($dispatch?->concretePump) {
            $pumpName = $dispatch->concretePump->registration ?: ($dispatch->concretePump->name ?? 'Dumping');
        } elseif (!empty($dispatch?->concrete_pump)) {
            if (is_numeric($dispatch->concrete_pump)) {
                $pumpMachine = \App\Models\Machine::find($dispatch->concrete_pump);
                $pumpName = $pumpMachine?->registration ?? (string)$dispatch->concrete_pump;
            } else {
                $pumpName = match(strtolower($dispatch->concrete_pump)) {
                    'line_pump' => 'Line Pump',
                    'boom_pump' => 'Boom Pump',
                    'static_pump', 'stationary_pump' => 'Stationary / Static Pump',
                    'dumping' => 'Dumping',
                    default => ucwords(str_replace('_', ' ', $dispatch->concrete_pump))
                };
            }
        } elseif ($salesOrder?->concrete_pump) {
            $pumpName = ucwords(str_replace('_', ' ', $salesOrder->concrete_pump));
        }

        // Resolve design mix reference
        $mixDesignObj = $dispatch?->mixDesign ?: ($salesOrder?->mixDesign ?: null);
        $designMixRef = self::resolveMixDesignName($mixDesignObj);

        // Resolve carrier / driver details
        $transportName = $dispatch?->transport?->name ?? ($invoice->plant?->name ?? '');
        $truckReg = $dispatch?->truck?->registration ?? '';
        $driverName = $dispatch?->driver ? trim(($dispatch->driver->first_name ?? '') . ' ' . ($dispatch->driver->last_name ?? '')) : '';
        
        $carrierDriverParts = array_filter([$transportName, $truckReg, $driverName]);
        $carrierDriverText = !empty($carrierDriverParts) ? implode(' , ', $carrierDriverParts) : '-';

        $isIntra = self::isIntraState($invoice->plant->gstin ?? '', $partner?->gstin ?? '');

        $showPumpCharges = !isset($data['settings']['pdf']['show_pump_charges']) || $data['settings']['pdf']['show_pump_charges'];

        $pumpChargesTotal = 0.0;
        $dispatchPumpCharge = 0.0;
        if ($dispatch) {
            $dispatchPumpCharge = (float)($dispatch->pump_charges ?? 0.0);
        }

        $data['items'] = $invoice->items->map(function ($item, $idx) use ($isIntra, $showPumpCharges, $dispatch, $dispatchPumpCharge, &$pumpChargesTotal) {
            $taxModel = $item->tax;
            $lineTaxAmount = (float)$item->line_tax_amount;

            // Fallback: if no direct tax relationship, derive from itemTaxes (order_taxes splits)
            if (!$taxModel && $item->relationLoaded('itemTaxes') && $item->itemTaxes->isNotEmpty()) {
                $splits = $item->itemTaxes;
                $lineTaxAmount = (float)$splits->sum('amount');
                $totalRate = (float)$splits->sum('rate');
                $groupNames = $splits->pluck('name')->filter()->implode(' + ');
                // Determine the parent tax group from the split names
                $firstSplitName = strtolower($splits->first()->name ?? '');
                if (str_contains($firstSplitName, 'igst')) {
                    $taxGroup = 'IGST';
                } elseif (str_contains($firstSplitName, 'cgst') || str_contains($firstSplitName, 'sgst')) {
                    $taxGroup = 'GST';
                } else {
                    $taxGroup = $isIntra ? 'GST' : 'IGST';
                }
                $taxName = $taxGroup . ' ' . ($totalRate == floor($totalRate) ? (int)$totalRate : $totalRate) . '%';
                $taxDetails = ['rate' => $totalRate, 'group' => $taxGroup, 'name' => $taxName];
            } else {
                $taxDetails = self::resolveTaxDetails($taxModel, $isIntra, $lineTaxAmount, (float)$item->subtotal);
            }

            $operationType = '-';
            $pumpCharge = 0.0;
            if ($showPumpCharges && $dispatch) {
                if ($dispatch->concretePump) {
                    $operationType = $dispatch->concretePump->registration;
                } elseif (!empty($dispatch->concrete_pump)) {
                    if (is_numeric($dispatch->concrete_pump)) {
                        $pumpMachine = \App\Models\Machine::find($dispatch->concrete_pump);
                        $operationType = $pumpMachine?->registration ?? (string)$dispatch->concrete_pump;
                    } else {
                        $operationType = match(strtolower($dispatch->concrete_pump)) {
                            'line_pump' => 'Line Pump',
                            'boom_pump' => 'Boom Pump',
                            'static_pump', 'stationary_pump' => 'Stationary / Static Pump',
                            default => ucwords(str_replace('_', ' ', $dispatch->concrete_pump))
                        };
                    }
                }
                $pumpCharge = $dispatchPumpCharge;
            }

            $pumpChargesTotal += $dispatchPumpCharge;

            return [
                'no' => $idx + 1, 'name' => $item->item_name, 'description' => '', 'hsn' => $item->hsn_code ?? '-',
                'qty' => (float)$item->quantity, 'unit' => $item->uom->unit_code ?? 'm³', 'unit_price' => (float)$item->price_unit,
                'operation_type' => $operationType, 'pump_charge' => $pumpCharge,
                'tax_name' => $taxDetails['name'] ?: '-', 'tax_rate' => $taxDetails['rate'], 'tax_group' => $taxDetails['group'], 'tax_amount' => $lineTaxAmount,
                'total' => (float)($item->line_total ?? ($item->quantity * $item->price_unit)),
            ];
        })->toArray();
        
        $taxLines = $invoice->orderTaxes->map(function($ot) { return ['label' => $ot->name, 'amount' => (float)$ot->amount]; })->toArray();
        $data['totals'] = [
            'sub_total' => (float)$invoice->subtotal, 'discount' => (float)($invoice->discount_total ?? $invoice->global_discount),
            'tax_lines' => $taxLines, 'shipping' => (float)$invoice->shipping_charges, 'adjustment' => (float)$invoice->adjustment,
            'round_off' => (float)$invoice->round_off,
            'grand_total' => (float)$invoice->total_amount,
        ];
        $orderTypeForTerms = $invoice->invoice_type === 'bill' ? 'Purchase Bill' : [($invoice->invoice_label ?? 'Tax Invoice'), 'Tax Invoice'];

        $poNumber = $customerPO?->customer_po_reference ?: ($customerPO?->reference ?? '');

        // TODO: Remove $testDummyQrPath before pushing to production
        $testDummyQrPath = asset('storage/plants/demo-mining-corp/parker-llc-plant/upi_qr_1784092752.png');

        $data['meta'] = [
            'currency_code'         => 'INR',
            'currency_symbol'       => '₹',
            'notes'                 => $invoice->notes ?? '',
            'terms_text'            => self::resolveTermsCondition($data['settings'], $orderTypeForTerms, $invoice->plant_id, "1. Goods once sold will not be taken back.\n2. Interest @ 18% will be charged if not paid within due date.\n3. All disputes are subject to local jurisdiction."),
            'total_words'           => self::numberToWords($invoice->total_amount, 'INR'),
            'po_number'             => $poNumber,
            'project_name'          => $invoice->ref_title ?? '',
            'irn'                  => $invoice->einvoice_irn ?? '',
            'ack_no'                => $invoice->einvoice_ack_no ?? '',
            'ack_date'              => $invoice->einvoice_ack_date?->format('d/m/Y') ?? '',
            'qr_code'               => self::formatQrCodeUrl(
                $invoice->einvoice_qr_code,
                $invoice->einvoice_irn,
                $testDummyQrPath
            ),
            'eway_bill_no'          => $invoice->eway_bill_no ?? '',
            'so_no'                 => $salesOrder ? $salesOrder->full_number : '',
            'acc_no'                => $partner?->account_number ?? $partner?->code ?? ($partner ? 'AC-' . $partner->id : ''),
            'sales_person'          => $salesPersonName,
            'pump'                  => $pumpName,
            'quality_incharge'      => $dispatch?->quality_incharge ?? '',
            'design_mix_ref'        => $designMixRef,
            'carrier_driver'        => $carrierDriverText,
            'sales_executive_name'   => $salesPersonName,
            'sales_executive_mobile' => $salesExec?->mobile_number ?? $salesExec?->phone ?? '',
        ];

        return $data;
    }

    public static function fromQuotation($quotation, ?array $customSettings = null): array
    {
        $statusLabels = [
            0 => 'DRAFT',
            1 => 'SENT',
            2 => 'ACCEPTED',
            3 => 'REJECTED'
        ];
        $state = $statusLabels[$quotation->status] ?? 'DRAFT';

        return self::fromQuotationOrCustomerPO(
            $quotation,
            'quotations',
            'QUOTATION',
            $quotation->reference ?? $quotation->id,
            $quotation->quote_date?->format('d/m/Y'),
            $quotation->validity_date?->format('d/m/Y'),
            $state,
            $customSettings
        );
    }

    public static function fromCustomerPO($customerPO, ?array $customSettings = null): array
    {
        $statusLabels = [
            0 => 'DRAFT',
            1 => 'CONFIRMED',
            2 => 'COMPLETED'
        ];
        $state = $statusLabels[$customerPO->status] ?? 'DRAFT';

        return self::fromQuotationOrCustomerPO(
            $customerPO,
            'customer_pos',
            'CUSTOMER PO',
            $customerPO->reference ?? $customerPO->id,
            $customerPO->order_date?->format('d/m/Y'),
            $customerPO->due_date?->format('d/m/Y'),
            $state,
            $customSettings
        );
    }

    private static function fromQuotationOrCustomerPO($model, string $module, string $defaultTitle, string $docNo, ?string $docDate, ?string $dueDate, string $state, ?array $customSettings = null): array
    {
        $model->loadMissing([
            'items.mixDesign',
            'items.mixDesign.concrete_grade',
            'items.mixDesign.concreteGrade',
            'items.mixDesign.items.product',
            'items.mixDesign.items.uom',
            'items.mixDesign.unit',
            'items.uom',
            'items.tax',
            'patron',
            'patron.addresses',
            'patron.contacts.addresses',
            'site',
            'plant',
            'plant.entity',
            'plant.addresses',
            'tax',
            'salesExecutive',
        ]);

        $data = self::base();
        $data['settings'] = $customSettings ?? self::getCustomSettings($model->plant_id, $module);
        if (!$customSettings && $module === 'customer_pos' && empty(\App\Models\CustomSetting::getForModule($model->plant_id, 'customer_pos'))) {
            $data['settings'] = self::getCustomSettings($model->plant_id, 'quotations');
        }

        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? $defaultTitle;
        $data['doc_no']    = $docNo;
        $data['doc_date']  = $docDate ?? now()->format('d/m/Y');
        $data['due_date']  = $dueDate ?? 'N/A';
        $data['state']     = $state;
        
        $isTaxInclusive = (bool)($model->is_tax_inclusive ?? false);
        $data['is_tax_inclusive'] = $isTaxInclusive;
        
        $data['company'] = self::formatCompany($model->plant);
        $data['bill_to'] = self::formatPartner($model->patron);
        $data['ship_to'] = self::formatShipTo($model->site, $data['bill_to']);

        $isIntra = self::isIntraState($model->plant->gstin ?? '', $model->patron->gstin ?? '');

        $settings = \App\Models\CustomSetting::getForModule($model->plant_id, 'batching');

        // Determine if selected concrete pump is boom or manual
        $isBoom = false;
        $isManual = false;
        if ($model->concretePump) {
            $isBoom = str_contains(strtolower($model->concretePump->vehicle_type ?? ''), 'boom')
                   || str_contains(strtolower($model->concretePump->registration ?? ''), 'boom');
            $isManual = str_contains(strtolower($model->concretePump->vehicle_type ?? ''), 'manual')
                     || str_contains(strtolower($model->concretePump->registration ?? ''), 'manual');
        }

        $selectedRate = 0.0;
        if ($model->concrete_pump) {
            if ($isManual) {
                $selectedRate = (float)($model->manual_rate ?? 0);
            } else {
                $selectedRate = $isBoom ? (float)($model->boom_pump_rate ?? 0) : (float)($model->pump_rate ?? 0);
            }
        } else {
            $selectedRate = (float)($model->manual_rate ?? 0);
        }

        $itemPumpRate = 0.0;
        $itemPumpRate = $selectedRate;

        $subtotalAmt = 0.0;
        $totalTaxAmt = 0.0;
        $grandTotalAmt = 0.0;

        $data['items'] = $model->items->map(function ($item, $idx) use ($model, $isIntra, $isTaxInclusive, $selectedRate, &$subtotalAmt, &$totalTaxAmt, &$grandTotalAmt) {
            $actualItemPumpRate = (float)($item->pump_rate ?? 0);
            $pumpTypeLabel = '-';
            
            if ($item->concrete_pump || $model->concrete_pump) {
                if ($actualItemPumpRate === 0.0) {
                    $actualItemPumpRate = $selectedRate;
                }
                $pumpVal = $item->concrete_pump ?? $model->concrete_pump;
                $rawType = (string)$pumpVal;
                $pumpTypeLabel = match(strtolower($rawType)) {
                    'line_pump' => 'Line Pump',
                    'boom_pump' => 'Boom Pump',
                    'static_pump', 'stationary_pump' => 'Stationary / Static Pump',
                    default => ucwords(str_replace('_', ' ', $rawType))
                };
            }

            $rate = (float)$item->rate;
            $qty = (float)$item->quantity;
            $taxModel = $item->tax;
            $taxRate = $taxModel ? (float)($taxModel->tax_rate ?? $taxModel->rate ?? 0) : 0.0;

            // Flat pump rate post-tax
            $linePumpTotal = $actualItemPumpRate;

            $lineTotal = 0.0;
            $lineTax = 0.0;
            $lineUntaxed = 0.0;

            if ($isTaxInclusive) {
                $lineTotal = ($rate * $qty);
                $lineTax = $lineTotal - ($lineTotal / (1 + $taxRate / 100));
                $lineUntaxed = $lineTotal - $lineTax + $linePumpTotal;
                $lineTotal = $lineTotal + $linePumpTotal;
            } else {
                $lineUntaxed = ($rate * $qty);
                $lineTax = ($lineUntaxed * $taxRate) / 100;
                $lineTotal = $lineUntaxed + $lineTax + $linePumpTotal;
                $lineUntaxed = $lineUntaxed + $linePumpTotal;
            }

            $subtotalAmt += $lineUntaxed;
            $totalTaxAmt += $lineTax;
            $grandTotalAmt += $lineTotal;

            $taxDetails = self::resolveTaxDetails($taxModel, $isIntra, $lineTax, $lineUntaxed);
            $unitPrice = $isTaxInclusive ? (float)($qty > 0 ? ($lineUntaxed / $qty) : $rate) : $rate;

            $showPumpCharges = !isset($data['settings']['pdf']['show_pump_charges']) || $data['settings']['pdf']['show_pump_charges'];

            if ($showPumpCharges) {
                $displayUnitPrice = $unitPrice - ($isTaxInclusive ? (float)($actualItemPumpRate / (1 + $taxRate / 100)) : (float)$actualItemPumpRate);
                $displayPumpCharge = $actualItemPumpRate;
            } else {
                $displayUnitPrice = $unitPrice;
                $displayPumpCharge = 0.0;
                $pumpTypeLabel = '-';
            }

            $itemDescription = self::formatMixDesignDescription($item->description, $item->mixDesign);

            return [
                'no' => $idx + 1,
                'name' => self::resolveMixDesignName($item->mixDesign),
                'description' => $itemDescription,
                'hsn' => $item->mixDesign->hsn_code ?? '-',
                'qty' => $qty,
                'received_qty' => 0,
                'unit' => $item->mixDesign->unit->unit_code ?? 'm³',
                'unit_price' => $displayUnitPrice,
                'operation_type' => $pumpTypeLabel,
                'pump_charge' => $displayPumpCharge,
                'tax_name' => $taxDetails['name'] ?: '-',
                'tax_rate' => $taxDetails['rate'],
                'tax_group' => $taxDetails['group'],
                'tax_amount' => (float)$lineTax,
                'total' => (float)$lineTotal,
                'pump_rates' => [],
                'recipe_materials' => self::resolveRecipeMaterials($item->mixDesign),
            ];
        })->toArray();

        // Quotation level pouring rates/settings are obsolete, removed to prevent duplicates.
        $pouringCharge = 0;

        // Compile tax lines
        $taxLines = [];
        foreach ($data['items'] as $item) {
            $taxAmt = $item['tax_amount'];
            if ($taxAmt > 0) {
                $g = strtoupper(trim($item['tax_group'] ?: ($isIntra ? 'GST' : 'IGST')));
                if ($g === 'GST') {
                    $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($taxAmt / 2);
                    $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($taxAmt / 2);
                } else {
                    $taxLines[$g] = ($taxLines[$g] ?? 0) + $taxAmt;
                }
            }
        }
        $formattedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();

        $adjustment = (float)($model->adjustment ?? 0);
        $finalGrandTotal = $grandTotalAmt + $adjustment;

        // Parse settings
        $isFlatRate = false;
        $chargeTypeLabel = 'per m³';

        $ratesTableHtml = '';

        $data['totals'] = [
            'sub_total'   => (float)$subtotalAmt,
            'discount'    => 0,
            'tax_lines'   => $formattedTaxLines,
            'shipping'    => 0,
            'adjustment'  => $adjustment,
            'round_off'   => 0,
            'pump_rate'   => 0.0, // Set to 0 so it is not added to totals breakdown list
            'pump_charges_total' => 0.0,
            'grand_total' => (float)$finalGrandTotal,
            'rates_table_html' => $ratesTableHtml,
        ];

        $termsText = self::resolveTermsCondition($data['settings'], ($module === 'customer_pos' ? 'Customer PO' : 'Quotation'), $model->plant_id, $model->terms_conditions ?? '');

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => $model->notes ?? '',
            'terms_text'      => $ratesTableHtml . $termsText,
            'total_words'     => self::numberToWords($finalGrandTotal, 'INR'),
            'sales_executive_name' => $model->salesExecutive?->full_name ?? '',
            'sales_executive_mobile' => $model->salesExecutive?->mobile ?? '',
        ];

        return $data;
    }

    public static function fromSalesOrder($salesOrder): array
    {
        $salesOrder->loadMissing(['customer','customer.addresses','customer.contacts.addresses','site','plant','plant.entity','plant.addresses','mixDesign','mixDesign.concrete_grade','mixDesign.concreteGrade','mixDesign.unit','mixDesign.items.product','mixDesign.items.uom','tax','customerPO.items.mixDesign','customerPO.items.mixDesign.concrete_grade','customerPO.items.mixDesign.concreteGrade','customerPO.items.tax','customerPO.quotation.items.mixDesign','customerPO.quotation.items.mixDesign.concrete_grade','customerPO.quotation.items.mixDesign.concreteGrade','customerPO.quotation.items.tax','customerPO.quotation.items.mixDesign.items.product','customerPO.quotation.items.mixDesign.items.uom']);
        $data = self::base();
        $data['settings'] = self::getCustomSettings($salesOrder->plant_id, 'sales_orders') ?: self::getCustomSettings($salesOrder->plant_id, 'quotations');
        $data['doc_title']  = $data['settings']['pdf']['labels']['invoice_title'] ?? 'SALES ORDER';
        $data['doc_no']     = ($salesOrder->prefix ?? '') . ($salesOrder->order_no ?? $salesOrder->id);
        $data['doc_date']   = $salesOrder->created_at ? $salesOrder->created_at->format('d/m/Y') : now()->format('d/m/Y');
        $data['due_date']   = $salesOrder->scheduled_end ? \Carbon\Carbon::parse($salesOrder->scheduled_end)->format('d/m/Y') : '';
        $statusMap = [1 => 'SCHEDULED', 2 => 'IN PROGRESS', 3 => 'COMPLETED', 4 => 'CANCELLED'];
        $data['state'] = $statusMap[$salesOrder->status] ?? 'DRAFT';
        
        $data['company'] = self::formatCompany($salesOrder->plant);
        $data['bill_to'] = self::formatPartner($salesOrder->customer);
        $data['ship_to'] = self::formatShipTo($salesOrder->site, $data['bill_to']);

        $isTaxInclusive = (bool)($salesOrder->is_tax_inclusive ?? ($salesOrder->customerPO?->is_tax_inclusive ?? false));
        $isIntra     = self::isIntraState($salesOrder->plant->gstin ?? '', $salesOrder->customer?->gstin ?? '');
        $quotationItems = $salesOrder->customerPO?->quotation?->items ?? collect();
        if ($quotationItems->isNotEmpty()) {
            $data['items'] = $quotationItems->map(function ($item, $idx) use ($isIntra, $isTaxInclusive, $salesOrder) {
                $subtotal  = (float)($item->untaxed_amount ?? ($item->quantity * $item->rate));
                $taxDetails = self::resolveTaxDetails($item->tax, $isIntra, (float)$item->tax_amount, $subtotal);
                $description = self::formatMixDesignDescription($item->description, $item->mixDesign);
                $unitPrice = $isTaxInclusive ? (float)($item->quantity > 0 ? ($subtotal / $item->quantity) : $item->rate) : (float)$item->rate;
                return [
                    'no' => $idx + 1, 'name' => self::resolveMixDesignName($item->mixDesign), 'description' => $description,
                    'hsn' => $item->mixDesign?->hsn_code ?? '-', 'qty' => (float)$item->quantity, 'received_qty'=> (float)($salesOrder->produced_qty ?? 0),
                    'unit' => $item->mixDesign?->unit?->unit_code ?? 'm³', 'unit_price' => $unitPrice, 'tax_name' => $taxDetails['name'] ?: '-',
                    'tax_rate' => $taxDetails['rate'], 'tax_group' => $taxDetails['group'], 'tax_amount' => (float)$item->tax_amount, 'total' => (float)($item->amount_total ?? ($item->quantity * $item->rate)),
                    'recipe_materials' => self::resolveRecipeMaterials($item->mixDesign),
                ];
            })->toArray();
        } else {
            $mixDesign = $salesOrder->mixDesign; $qty  = (float)($salesOrder->total_qty ?? 0);
            $poItem = null; if ($salesOrder->customerPO) $poItem = $salesOrder->customerPO->items->where('mix_design_id', $salesOrder->mix_design_id)->first();
            $rate = (float)($salesOrder->rate ?? ($poItem ? $poItem->rate : ($mixDesign?->rate_per_qty ?? 0)));
            $taxModel = $salesOrder->tax ?? $poItem?->tax;
            $taxRate  = 0.0; $taxGroup = ''; $taxName  = '-'; $priceTax = 0.0;
            if ($taxModel) {
                $taxRate  = (float)($taxModel->tax_rate ?? $taxModel->rate ?? 0);
                $taxGroup = $taxModel->tax_group ?? '';
                $taxName  = $taxModel->tax_name ?? '';
            }
            $subtotal = $qty * $rate;
            if ($isTaxInclusive) {
                $total = $subtotal;
                $untaxedAmt = $taxRate > 0 ? ($total / (1 + ($taxRate / 100))) : $total;
                $priceTax = $total - $untaxedAmt;
                $unitPrice = $qty > 0 ? ($untaxedAmt / $qty) : $rate;
            } else {
                $untaxedAmt = $subtotal;
                $priceTax = $untaxedAmt * ($taxRate / 100);
                $total = $untaxedAmt + $priceTax;
                $unitPrice = $rate;
            }
            if ($taxRate <= 0 && $poItem) {
                $itemTaxAmount = (float)$poItem->tax_amount; $itemUntaxedAmount = (float)($poItem->untaxed_amount ?? ($poItem->quantity * $poItem->rate));
                if ($itemTaxAmount > 0 && $itemUntaxedAmount > 0) {
                    $taxRate = round(($itemTaxAmount / $itemUntaxedAmount) * 100, 2); $taxGroup = $isIntra ? 'GST' : 'IGST';
                    $taxName  = $taxGroup . ' ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
                    if ($isTaxInclusive) { $untaxedAmt = $total / (1 + ($taxRate / 100)); $priceTax = $total - $untaxedAmt; $unitPrice = $qty > 0 ? ($untaxedAmt / $qty) : $rate; }
                    else { $priceTax = $untaxedAmt * ($taxRate / 100); $total = $untaxedAmt + $priceTax; }
                }
            }
            $taxDetails = self::resolveTaxDetails($taxModel, $isIntra, $priceTax, $untaxedAmt);
            if ($taxRate > 0 && empty($taxDetails['name'])) {
                $taxDetails['name'] = ($taxGroup ?: ($isIntra ? 'GST' : 'IGST')) . ' ' . ($taxRate == floor($taxRate) ? (int)$taxRate : $taxRate) . '%';
            }
            $description = self::formatMixDesignDescription('', $mixDesign);
            $data['items'] = $mixDesign ? [[
                'no' => 1, 'name' => self::resolveMixDesignName($mixDesign), 'description' => $description,
                'hsn' => $mixDesign->hsn_code ?? '-', 'qty' => $qty, 'received_qty'=> (float)($salesOrder->produced_qty ?? 0),
                'unit' => $mixDesign->unit?->unit_code ?? 'm³', 'unit_price' => $unitPrice, 'tax_name' => $taxDetails['name'] ?: ($taxName ?: '-'),
                'tax_rate' => $taxDetails['rate'] ?: $taxRate, 'tax_group' => $taxDetails['group'] ?: $taxGroup, 'tax_amount' => $priceTax, 'total' => $total,
                'recipe_materials' => self::resolveRecipeMaterials($mixDesign),
            ]] : [];
        }

        // Resolve concrete_pump and pump_rate details for sales order
        $concretePump = $salesOrder->concrete_pump;
        $pumpRate = (float)($salesOrder->pump_rate ?? 0);
        $showPumpCharges = !isset($data['settings']['pdf']['show_pump_charges']) || $data['settings']['pdf']['show_pump_charges'];

        $pumpTypeLabel = '-';
        if ($concretePump) {
            $rawType = (string)$concretePump;
            $pumpTypeLabel = match(strtolower($rawType)) {
                'line_pump' => 'Line Pump',
                'boom_pump' => 'Boom Pump',
                'static_pump', 'stationary_pump' => 'Stationary / Static Pump',
                'manual' => 'Manual',
                'dumping' => 'Dumping',
                default => ucwords(str_replace('_', ' ', $rawType))
            };
        }

        $itemsCount = count($data['items']);
        if ($itemsCount > 0) {
            foreach ($data['items'] as $i => &$item) {
                $item['operation_type'] = $showPumpCharges ? $pumpTypeLabel : '-';
                // Allocate pump_rate as pump_charge across items (if 1 item, whole pumpRate; else per-item/split)
                $item['pump_charge'] = ($showPumpCharges && $i === 0) ? $pumpRate : 0.0;
            }
            unset($item);
        }

        // Add pump rate flat charge if configured & enabled
        $showPumpRate = (!isset($data['settings']['pdf']['pump_rates']) || $data['settings']['pdf']['pump_rates']) && $pumpRate > 0;

        $pumpRateUntaxed = 0.0;
        $pumpRateTax = 0.0;
        $pumpRateTotal = 0.0;
        $taxGroup = '';

        if ($showPumpRate) {
            $firstItem = collect($data['items'])->first();
            $taxRate = 0.0;
            if ($firstItem) {
                $taxRate = (float)($firstItem['tax_rate'] ?? 0);
                $taxGroup = $firstItem['tax_group'] ?? '';
            }

            if ($isTaxInclusive) {
                $pumpRateTotal = $pumpRate;
                $pumpRateTax = $pumpRateTotal - ($pumpRateTotal / (1 + $taxRate / 100));
                $pumpRateUntaxed = $pumpRateTotal - $pumpRateTax;
            } else {
                $pumpRateUntaxed = $pumpRate;
                $pumpRateTax = $pumpRateUntaxed * ($taxRate / 100);
                $pumpRateTotal = $pumpRateUntaxed + $pumpRateTax;
            }
        }

        // Compile/Recalculate totals
        $subTotalVal = 0.0;
        $grandTotalVal = 0.0;
        $taxLines = [];
        
        foreach ($data['items'] as $item) {
            if ($isTaxInclusive) {
                $subTotalVal += ((float)$item['total'] - (float)$item['tax_amount']);
            } else {
                $subTotalVal += ((float)$item['qty'] * (float)$item['unit_price']);
            }
            $grandTotalVal += (float)$item['total'];

            $taxAmt = (float)$item['tax_amount'];
            if ($taxAmt > 0) {
                $g = strtoupper(trim($item['tax_group'] ?: ($isIntra ? 'GST' : 'IGST')));
                if ($g === 'GST') {
                    $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($taxAmt / 2);
                    $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($taxAmt / 2);
                } else {
                    $taxLines[$g] = ($taxLines[$g] ?? 0) + $taxAmt;
                }
            }
        }

        // Add the pump rate tax to the tax lines
        if ($showPumpRate && $pumpRateTax > 0) {
            $g = strtoupper(trim($taxGroup ?: ($isIntra ? 'GST' : 'IGST')));
            if ($g === 'GST') {
                $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($pumpRateTax / 2);
                $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($pumpRateTax / 2);
            } else {
                $taxLines[$g] = ($taxLines[$g] ?? 0) + $pumpRateTax;
            }
        }

        $computedTaxLines = collect($taxLines)->map(fn($amt, $lbl) => ['label' => $lbl, 'amount' => $amt])->values()->toArray();
        $data['totals'] = [
            'sub_total'   => (float)$subTotalVal,
            'pump_rate'   => (float)$pumpRateUntaxed,
            'discount'    => 0,
            'tax_lines'   => $computedTaxLines,
            'shipping'    => 0,
            'adjustment'  => 0,
            'round_off'   => 0,
            'grand_total' => (float)($grandTotalVal + $pumpRateTotal)
        ];

        $salesExec = $salesOrder->salesExecutive;
        $salesPersonName = $salesExec ? trim(($salesExec->first_name ?? '') . ' ' . ($salesExec->last_name ?? '')) : '';

        $data['meta'] = [
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'notes' => $salesOrder->terms_conditions ?? '',
            'terms_text' => self::resolveTermsCondition($data['settings'], 'Sales Order', $salesOrder->plant_id, $salesOrder->terms_conditions ?? ''),
            'total_words' => self::numberToWords($data['totals']['grand_total'], 'INR'),
            'po_number' => $salesOrder->customerPO?->customer_po_reference ?: ($salesOrder->customerPO?->reference ?? ''),
            'project_name' => $salesOrder->site?->name ?? '',
            'pump' => $pumpTypeLabel !== '-' ? $pumpTypeLabel : '',
            'sales_person' => $salesPersonName,
            'sales_executive_name' => $salesPersonName,
            'sales_executive_mobile' => $salesExec?->mobile_number ?? $salesExec?->phone ?? '',
        ];
        return $data;
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
        
        $data['company'] = self::formatCompany($batch->salesOrder->plant);
        $data['bill_to'] = self::formatPartner($batch->salesOrder->customer);
        $data['ship_to'] = self::formatShipTo($batch->salesOrder->site, $data['bill_to']);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');
        $printMode = $settings['material_print_mode'] ?? 'run';
        $formattedMaterials = $batch->getFormattedMaterials($printMode);

        $groupedMaterials = $formattedMaterials->groupBy(function($mat){ return $mat->material_name; })->map(function($group){
            $first = $group->first(); $target = $group->sum('target_qty'); $actual = $group->sum('actual_qty');
            return (object)['material_name'=>$first->material_name,'uom_code'=>$first->uom_code,'hsn_code'=>'-','target_qty'=>$target,'actual_qty'=>$actual,'deviation_quantity'=>$group->sum('deviation_quantity')];
        });
        $itemsList = [];
        if ($dispatch) {
            $mixDesign = $batch->salesOrder?->mixDesign;
            $mixDesignName = self::resolveMixDesignName($mixDesign);
            $qty = (float)($dispatch->delivered_qty ?: $batch->batch_size); $rate = (float)($dispatch->load_rate ?? 0);
            $subTotal = (float)($dispatch->load_untax_amount ?? ($qty * $rate)); $taxAmount = (float)($dispatch->load_tax_amount ?? 0);
            $totalAmount = (float)($dispatch->load_total_amount ?? ($subTotal + $taxAmount)); $taxRate = $dispatch->loadTax?->rate ?? 0; $taxName = $dispatch->loadTax?->name ?? '-';
            $itemsList[] = ['no'=>1,'name'=>$mixDesignName,'description'=>'','hsn'=>'3824','qty'=>$qty,'received_qty'=>$qty,'unit'=>$dispatch->uom?->unit_code ?? 'CBM','unit_price'=>$rate,'tax_name'=>$taxName,'tax_rate'=>$taxRate,'tax_amount'=>$taxAmount,'total'=>$totalAmount];
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
        $emptyWeight = (float) ($dispatch?->empty_weight_truck ?? 0); $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
        $netWeight = (float) ($dispatch?->net_weight ?? ($loadedWeight - $emptyWeight));
        $unitLabel = ' kg'; $decimals = 0;
        $emptyWeightStr = number_format($emptyWeight, $decimals) . $unitLabel; $loadedWeightStr = number_format($loadedWeight, $decimals) . $unitLabel; $netWeightStr = number_format($netWeight, $decimals) . $unitLabel;
        $weightNotes = "VEHICLE WEIGHT DETAILS:\n" . "Truck No: " . ($dispatch?->truck?->registration ?? '-') . "\n" . "Driver: " . (trim(($dispatch?->driver?->first_name ?? '') . ' ' . ($dispatch?->driver?->last_name ?? '')) ?: '-') . "\n" . "Empty Weight: " . $emptyWeightStr . " (" . ($dispatch?->empty_time ? \Carbon\Carbon::parse($dispatch->empty_time)->format('d-m-Y H:i') : '-') . ")\n" . "Loaded Weight: " . $loadedWeightStr . " (" . ($dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('d-m-Y H:i') : '-') . ")\n" . "Net Weight: " . $netWeightStr . "\n\n" . "Batch size: " . number_format((float) $batch->batch_size, 2) . " m³\n" . "Concrete Grade: " . self::resolveMixDesignName($batch->salesOrder?->mixDesign);
        $data['meta'] = [
            'currency_code'=>'INR','currency_symbol'=>'₹','notes'=>$weightNotes,
            'terms_text'=>self::resolveTermsCondition($data['settings'], 'Delivery Challan', $batch->salesOrder->plant_id, $batch->salesOrder?->terms_conditions ?? "1. Goods received in good condition.\n2. Any variation in quantity to be reported immediately."),
            'total_words'=>'','po_number'=>$batch->salesOrder?->order_no ?? '-','project_name'=>'Concrete Grade: ' . self::resolveMixDesignName($batch->salesOrder?->mixDesign),
        ];
        $data['batch'] = $batch;
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

    // Converts 0 to 99,99,999 (no Crore word) - used for rem count
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
        if (!$setting && $moduleKey === 'customer_pos') {
            $setting = PrintTemplateSetting::where('module_key', 'quotations')->where('plant_id', $plantId)->with('template')->first();
        }
        return $setting?->template?->key ?? 'standard';
    }

    public static function supportedTemplates(): array
    {
        return ['standard','box_layout','elite','modern','spreadsheet','tallysheet','compact','indian_gst','formal_gst','standard_indigo','minimalist_lite','delivery_challan_a4'];
    }

    public static function resolveView(string $templateKey): string
    {
        if ($templateKey === 'delivery_challan_a4') return "pdfs.batches.delivery_token";
        $map = ['formal_gst' => 'indian_gst','standard_indigo' => 'elite','minimalist_lite' => 'compact'];
        $supported = self::supportedTemplates();
        $key = in_array($templateKey, $supported) ? ($map[$templateKey] ?? $templateKey) : 'standard';
        return "pdfs.templates.{$key}";
    }

    public static function getCustomSettings(int $plantId, string $module, ?string $templateKey = null): array
    {
        $defaults = self::getDefaultSettings($module);
        $baseStored = \App\Models\CustomSetting::getForModule($plantId, $module);
        return array_replace_recursive($defaults, $baseStored);
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
                'upi_qr'=>true,
                'pump_rates'=>true,
                'labels' => ['invoice_title'=>$invoiceTitle,'bill_to'=>'Bill To','ship_to'=>'Ship To','rate'=>'Rate','amount'=>'Amount']
            ],
            'excel' => ['hsn_code'=>true,'discount'=>true]
        ];
    }

    /**
     * Safely resolve QR code into a renderable image URL.
     */
    public static function formatQrCodeUrl(?string $qrCode, ?string $irn = null, ?string $upiQrPath = null, ?string $defaultPath = null): string
    {
        if (!empty($qrCode)) {
            if (str_starts_with($qrCode, 'data:image') || str_starts_with($qrCode, 'http://') || str_starts_with($qrCode, 'https://')) {
                return $qrCode;
            }

            // Check if it's an image file path stored in storage/
            $cleanPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $qrCode), '/');
            if (preg_match('/\.(png|jpe?g|svg|webp)$/i', $cleanPath) && !str_contains($cleanPath, '{') && !str_contains($cleanPath, '}')) {
                return asset('storage/' . $cleanPath);
            }

            // Raw JSON / JWT / Text string -> generate dynamic QR code image URL
            return 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qrCode);
        }

        if (!empty($irn)) {
            return 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($irn);
        }

        if (!empty($upiQrPath)) {
            $cleanUpi = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $upiQrPath), '/');
            return asset('storage/' . $cleanUpi);
        }

        return $defaultPath ?? '';
    }
}