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

use App\Models\Batch;
use App\Models\ConcreteGrade;
use App\Models\CustomSetting;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\PrintTemplateSetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\CustomerPO;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\TermsCondition;
use Carbon\Carbon;
use Throwable;

class PrintDataFormatter
{
    // =========================================================================
    //  1. SCHEMA & BASE DEFAULTS
    // =========================================================================

    /**
     * Returns the base schema (empty / default values).
     */
    public static function base(): array
    {
        return [
            'doc_title'     => 'DOCUMENT',
            'doc_no'        => '',
            'doc_date'      => '',
            'due_date'      => '',
            'delivery_date' => '',
            'state'         => 'DRAFT',
            'terms'         => 'Net 30',
            'company'       => [
                'name' => '', 'address' => '', 'city' => '', 'state' => '', 'pin' => '', 'gstin' => '', 'phone' => '', 'email' => '',
            ],
            'bill_to'       => [
                'name' => '', 'address' => '', 'city' => '', 'state' => '', 'pin' => '', 'gstin' => '', 'phone' => '',
            ],
            'ship_to'       => [
                'name' => '', 'address' => '', 'city' => '', 'state' => '', 'pin' => '',
            ],
            'items'         => [],
            'totals'        => [
                'sub_total' => 0, 'discount' => 0, 'tax_lines' => [], 'shipping' => 0, 'adjustment' => 0, 'round_off' => 0, 'grand_total' => 0,
            ],
            'meta'          => [
                'po_number' => '', 'project_name' => '', 'currency_code' => 'INR', 'currency_symbol' => '₹',
                'notes' => '', 'terms_text' => '', 'total_words' => '', 'site_incharge' => '', 'contact_no' => '',
            ],
        ];
    }

    /**
     * Generates dummy preview payload from latest database record or dynamic fallback.
     */
    public static function dummy(string $category = 'invoice', ?array $customSettings = null): array
    {
        $plantId = session('active_plant_id') ?: 1;

        try {
            switch ($category) {
                case 'invoices':
                case 'gst_invoices':
                case 'billings':
                case 'purchase_bills':
                    $type = ($category === 'purchase_bills') ? 'bill' : 'invoice';
                    $invoice = Invoice::where('plant_id', $plantId)->where('invoice_type', $type)->latest()->first()
                        ?? Invoice::latest()->first();
                    if ($invoice) {
                        return self::fromInvoice($invoice);
                    }
                    break;

                case 'purchase_orders':
                    $po = PurchaseOrder::where('plant_id', $plantId)->latest()->first() ?? PurchaseOrder::latest()->first();
                    if ($po) {
                        return self::fromPurchaseOrder($po);
                    }
                    break;

                case 'quotations':
                    $quotation = Quotation::where('plant_id', $plantId)->latest()->first() ?? Quotation::latest()->first();
                    if ($quotation) {
                        return self::fromQuotation($quotation, $customSettings);
                    }
                    break;

                case 'customer_pos':
                    $cpo = CustomerPO::where('plant_id', $plantId)->latest()->first() ?? CustomerPO::latest()->first();
                    if ($cpo) {
                        return self::fromCustomerPO($cpo, $customSettings);
                    }
                    break;

                case 'sales_orders':
                    $so = SalesOrder::where('plant_id', $plantId)->latest()->first() ?? SalesOrder::latest()->first();
                    if ($so) {
                        if (empty($so->pump_rate) || (float) $so->pump_rate <= 0) {
                            $so->pump_rate = 1500.00;
                        }
                        return self::fromSalesOrder($so);
                    }
                    break;

                case 'delivery_challans':
                    $batch = Batch::whereHas('workOrder', fn ($q) => $q->where('plant_id', $plantId))->latest()->first()
                        ?? Batch::latest()->first();
                    if ($batch) {
                        return self::fromDeliveryChallan($batch);
                    }
                    break;
            }
        } catch (Throwable $e) {
            // Fallback to sample construction
        }

        $data = self::base();
        dd($data);
        $data['settings']      = self::getCustomSettings($plantId, $category);
        $data['doc_title']     = $data['settings']['pdf']['labels']['invoice_title'] ?? (strtoupper($category) . ' DOCUMENT');
        $data['doc_no']        = 'REF-' . now()->format('Y') . '-001';
        $data['doc_date']      = now()->format('d/m/Y');
        $data['due_date']      = now()->addDays(15)->format('d/m/Y');
        $data['delivery_date'] = now()->addDays(5)->format('d/m/Y');

        $plant = Plant::with(['entity', 'addresses'])->find($plantId) ?? Plant::with(['entity', 'addresses'])->first();
        $data['company'] = $plant ? self::formatCompany($plant) : [
            'name'    => 'ModoMines Tech Solutions', 'address' => '123 Cloud Avenue, Tech Park', 'city'    => 'Chennai',
            'state'   => 'Tamil Nadu', 'pin'     => '600001', 'gstin'   => '33AAAAA0000A1Z5', 'phone'   => '+91 98765 43210', 'email'   => 'support@modomines.com',
        ];

        $partner = Patron::where('plant_id', $plantId)->first() ?? Patron::first();
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
                'name' => 'Alpha Prime Industries', 'address' => '45 Industrial Estate, Phase II', 'city' => 'Coimbatore',
                'state' => 'Tamil Nadu', 'pin' => '641001', 'gstin' => '33BBBBB1111B1Z2', 'phone' => '+91 422 2345678',
            ];
            $data['ship_to'] = [
                'name' => 'Alpha Prime - Site A', 'address' => 'Plot 88, Near New Bypass', 'city' => 'Salem', 'state' => 'Tamil Nadu', 'pin' => '636001',
            ];
        }

        $products = Product::with(['unit', 'saleTax'])->where('status', 'active')->limit(2)->get();
        if ($products->count() > 0) {
            $items = [];
            $subtotal = 0;
            $totalTax = 0;

            foreach ($products as $idx => $prod) {
                $qty = ($idx === 0) ? 25.0 : 2.0;
                $price = (float) ($prod->sales_price ?? $prod->purchase_price ?? 4500.00);
                if ($price <= 0) $price = 4500.00;

                $lineTotal = $qty * $price;
                $taxRate = $prod->saleTax ? (float) $prod->saleTax->tax_rate : 12.0;
                $taxAmount = ($lineTotal * $taxRate) / 100;

                $subtotal += $lineTotal;
                $totalTax += $taxAmount;

                $items[] = [
                    'no'           => $idx + 1,
                    'name'         => $prod->title ?? $prod->name ?? 'Product ' . ($idx + 1),
                    'description'  => $prod->description ?? $prod->title ?? '',
                    'hsn'          => $prod->hsn_code ?? '38245010',
                    'qty'          => $qty,
                    'received_qty' => $qty,
                    'unit'         => $prod->unit?->unit_code ?? $prod->uom?->unit_code ?? 'm³',
                    'unit_price'   => $price,
                    'tax_name'     => 'GST ' . (int) $taxRate . '%',
                    'tax_rate'     => $taxRate,
                    'tax_group'    => 'GST',
                    'tax_amount'   => $taxAmount,
                    'total'        => $lineTotal + $taxAmount,
                ];
            }

            $data['items'] = $items;
            $data['totals'] = [
                'sub_total'   => $subtotal,
                'discount'    => 524.00,
                'pump_charge' => 201.00,
                'hire_charge' => 5244.00,
                'pass_amount' => 4524.00,
                'tax_amount'  => $totalTax,
                'tax_lines'   => [
                    ['label' => 'CGST', 'amount' => $totalTax / 2],
                    ['label' => 'SGST', 'amount' => $totalTax / 2],
                ],
                'shipping'    => 0,
                'adjustment'  => 445.00,
                'round_off'   => 45.00,
                'grand_total' => $subtotal + 201.00 - 524.00 + 5244.00 + 4524.00 + $totalTax + 445.00 + 45.00,
            ];
            $data['meta']['total_words'] = self::numberToWords($data['totals']['grand_total'], 'INR');
        } else {
            $data['items'] = [
                [
                    'no' => 1, 'name' => 'High Grade Concrete Mix (M40)', 'description' => 'Standard grade for heavy structural works',
                    'hsn' => '38245010', 'qty' => 45.00, 'received_qty' => 45.00, 'unit' => 'm³', 'unit_price' => 4500.00,
                    'operation_type' => 'TM', 'pump_charge' => 201.00, 'discount' => 524.00, 'taxable_amount' => 201976.00,
                    'tax_name' => 'GST 12%', 'tax_rate' => 12, 'tax_group' => 'GST', 'tax_amount' => 24237.12, 'total' => 226213.12,
                ],
                [
                    'no' => 2, 'name' => 'Reinforcement Steel (12mm)', 'description' => 'TMT Bars - FE500D Grade',
                    'hsn' => '721420', 'qty' => 2.50, 'received_qty' => 0.00, 'unit' => 'MT', 'unit_price' => 62000.00,
                    'operation_type' => 'Manual', 'pump_charge' => 0.00, 'discount' => 0.00, 'taxable_amount' => 155000.00,
                    'tax_name' => 'GST 18%', 'tax_rate' => 18, 'tax_group' => 'GST', 'tax_amount' => 27900.00, 'total' => 182900.00,
                ],
            ];
            $data['totals'] = [
                'sub_total'   => 356976.00,
                'discount'    => 0.00,
                'pump_charge' => 201.00,
                'hire_charge' => 5244.00,
                'pass_amount' => 4524.00,
                'tax_amount'  => 52137.12,
                'tax_lines'   => [['label' => 'CGST', 'amount' => 26068.56], ['label' => 'SGST', 'amount' => 26068.56]],
                'shipping'    => 1200.00,
                'adjustment'  => 445.00,
                'round_off'   => 0.88,
                'grand_total' => 356976.00 + 201.00 + 5244.00 + 4524.00 + 52137.12 + 1200.00 + 445.00 + 0.88,
            ];
            $data['meta']['total_words'] = self::numberToWords($data['totals']['grand_total'], 'INR');
        }

        $data['meta']['project_name']           = $plant?->name ?? 'Grand Mall Construction - Phase 1';
        $data['meta']['po_number']              = 'PO-8877';
        $data['meta']['terms_text']             = self::resolveTermsCondition($data['settings'], 'Invoice', $plantId, "1. Payment within 15 days of delivery.\n2. Goods once sold will not be taken back.");
        $data['meta']['sales_executive_name']   = 'Sales Executive';
        $data['meta']['sales_executive_mobile'] = $plant?->phone ?? '';

        return $data;
    }

    // =========================================================================
    //  2. SETTINGS & TEMPLATES
    // =========================================================================

    public static function getCustomSettings(int $plantId, string $module, ?string $templateKey = null): array
    {
        $defaults = self::getDefaultSettings($module);
        $baseStored = CustomSetting::getForModule($plantId, $module);
        $batchingStored = CustomSetting::getForModule($plantId, 'batching');

        $settings = array_replace_recursive($defaults, ['batching' => $batchingStored], $batchingStored, $baseStored);
        if (in_array($module, ['purchase_bills', 'purchase_orders'], true)) {
            // Batching preferences must not add concrete/pump columns to purchases.
            $settings['pdf']['show_pump_charges'] = false;
            $settings['pdf']['pump_rates'] = false;
        }
        return $settings;
    }

    public static function getDefaultSettings(string $module): array
    {
        $invoiceTitle = match ($module) {
            'invoices', 'gst_invoices' => 'TAX INVOICE',
            'purchase_orders'         => 'PURCHASE ORDER',
            'purchase_bills'          => 'PURCHASE BILL',
            'quotations'               => 'QUOTATION',
            'customer_pos'             => 'CUSTOMER PO',
            'sales_orders'             => 'SALES ORDER',
            'delivery_challans'        => 'DELIVERY CHALLAN',
            'delivery_notes'           => 'DELIVERY NOTE',
            'credit_notes'             => 'CREDIT NOTE',
            'statements'               => 'STATEMENT OF ACCOUNT',
            default                   => 'DOCUMENT',
        };

        return [
            'pdf' => [
                'company_name'      => true, 'logo' => true, 'address' => true, 'phone' => true, 'email' => true, 'gstin' => true,
                'invoice_title'     => true, 'invoice_number' => true, 'date' => true, 'due_date' => true, 'status' => false,
                'bill_to'           => true, 'ship_to' => true, 'hsn_code' => true, 'description' => true, 'unit' => true, 'discount' => true,
                'amount'            => true,
                'tax_percent'       => true, 'cgst' => true, 'sgst' => true, 'igst' => true, 'adjustment' => true,
                'round_off'         => true, 'total_words' => true, 'notes' => true, 'terms' => true, 'signature' => true,
                'upi_qr'            => true,
                'pump_rates'        => !in_array($module, ['purchase_bills', 'purchase_orders'], true),
                'show_pump_charges' => !in_array($module, ['purchase_bills', 'purchase_orders'], true),
                'hire_charge'       => true,
                'pass_amount'       => true,
                'labels'            => ['invoice_title' => $invoiceTitle, 'bill_to' => 'Bill To', 'ship_to' => 'Ship To', 'rate' => 'Rate', 'amount' => 'Amount'],
            ],
            'excel' => ['hsn_code' => true, 'discount' => true],
        ];
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
        return ['standard', 'box_layout', 'elite', 'modern', 'spreadsheet', 'tallysheet', 'compact', 'indian_gst', 'formal_gst', 'standard_indigo', 'minimalist_lite', 'delivery_challan_a4'];
    }

    public static function resolveView(string $templateKey): string
    {
        if ($templateKey === 'delivery_challan_a4') return "pdfs.batches.delivery_token";
        $map = ['formal_gst' => 'indian_gst', 'standard_indigo' => 'elite', 'minimalist_lite' => 'compact'];
        $supported = self::supportedTemplates();
        $key = in_array($templateKey, $supported) ? ($map[$templateKey] ?? $templateKey) : 'standard';
        return "pdfs.templates.{$key}";
    }

    /**
     * Resolves configuring setting for item printed name (0 = Grade, 1 = Mix Design, 2 = Grade + Mix Design).
     */
    public static function getPrintItemNameFormat(array $settings, ?int $plantId = null): int
    {
        if (isset($settings['batching']['is_grade_or_is_mix_design'])) {
            return (int) $settings['batching']['is_grade_or_is_mix_design'];
        }

        if (isset($settings['is_grade_or_is_mix_design'])) {
            return (int) $settings['is_grade_or_is_mix_design'];
        }

        try {
            $pid = $plantId ?: (session('active_plant_id') ?: 1);
            $batchingSettings = CustomSetting::getForModule($pid, 'batching');
            if (isset($batchingSettings['is_grade_or_is_mix_design'])) {
                return (int) $batchingSettings['is_grade_or_is_mix_design'];
            }
        } catch (Throwable $e) {
        }

        return 0;
    }

    // =========================================================================
    //  3. COMPANY, PARTNER & ADDRESS FORMATTERS
    // =========================================================================

    public static function resolveBankDetails($plant, $entity = null): array
    {
        if (!empty($plant?->bank_name)) {
            return [
                'account_name'   => $plant?->bank_account_name ?? ($plant?->name ?? ''),
                'account_number' => $plant?->bank_account_number ?? '',
                'bank_name'      => $plant?->bank_name ?? '',
                'branch'         => $plant?->bank_branch ?? '',
                'ifsc_code'      => $plant?->ifsc_code ?? '',
            ];
        }

        $legalEntity = $entity ?? $plant?->entity;
        $primaryBank = $legalEntity?->bankAccounts?->firstWhere('is_primary', 1) ?? $legalEntity?->bankAccounts?->first();

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
        $plAddr    = $plant?->addresses?->first();
        $stateName = $plAddr?->state?->state_name ?? $plAddr?->state_code ?? '';
        $stateCode = $plAddr?->state?->state_code ?? ($plant?->gstin && strlen($plant->gstin) >= 2 ? substr($plant->gstin, 0, 2) : '');
        $gstin     = $plant?->gstin ?? '';
        $pan       = strlen($gstin) >= 14 ? substr($gstin, 2, 10) : ($plant?->pan ?? '');

        return [
            'name'           => $plant?->entity?->legal_name ?? $plant?->entity?->entity_name ?? $plant?->name ?? 'Company',
            'address'        => $plAddr?->line_1 ? $plAddr?->line_1 . ' ' . $plAddr?->line_2 : '',
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

        $partnerAddr    = null;
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

        $name    = $partner->legal_name ?: ($partner->name ?: 'N/A');
        $address = $partnerAddr?->line_1 ? $partnerAddr?->line_1 . ' ' . $partnerAddr?->line_2 : ($partner->address_line1 ?? '');
        $city    = $partnerAddr?->city ?: ($partner->city ?? '');

        $stateVal = '';
        if ($partnerAddr) {
            $stateVal = $partnerAddr->state?->state_name ?: ($partnerAddr->state_code ?? '');
        }
        if (empty($stateVal)) {
            $stateVal = $partner->state ?? '';
        }

        $pin   = $partnerAddr?->zipcode ?: ($partner->pincode ?? '');
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

    public static function formatSalesExecutiveName($salesExec): string
    {
        if (!$salesExec) {
            return '';
        }
        if (!empty($salesExec->full_name)) {
            return $salesExec->full_name;
        }
        return trim(($salesExec->first_name ?? '') . ' ' . ($salesExec->last_name ?? ''));
    }

    public static function formatSalesExecutiveMobile($salesExec): string
    {
        if (!$salesExec) {
            return '';
        }
        return $salesExec->mobile_number ?? $salesExec->mobile ?? $salesExec->phone ?? '';
    }

    public static function formatCarrierDriverDetails(?string $transportName, ?string $truckReg, $driver = null): string
    {
        $driverName = '';
        if ($driver) {
            $driverName = is_string($driver) ? $driver : trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''));
        }
        $parts = array_filter([$transportName, $truckReg, $driverName]);
        return !empty($parts) ? implode(' , ', $parts) : '-';
    }

    // =========================================================================
    //  4. MIX DESIGN & ITEM RESOLVERS
    // =========================================================================

    public static function resolvePrintedItemName($mixDesign, int $format = 0, ?string $fallback = null): string
    {
        if (!$mixDesign) {
            return $fallback ?? '-';
        }

        $gradeName = $mixDesign->concreteGrade?->name
            ?? $mixDesign->concrete_grade?->name
            ?? $mixDesign->grade
            ?? null;

        if (empty($gradeName) && !empty($mixDesign->concrete_grade_id)) {
            $gradeName = ConcreteGrade::find($mixDesign->concrete_grade_id)?->name;
        }

        $mixDesignName = self::resolveMixDesignName($mixDesign);

        $name = match ($format) {
            1       => $mixDesignName,
            2       => trim(implode(' - ', array_unique(array_filter([$gradeName, $mixDesignName])))),
            default => $gradeName ?? $mixDesignName,
        };

        return !empty($name) && $name !== '-' ? $name : ($fallback ?? '-');
    }

    public static function resolveMixDesignName($mixDesign): string
    {
        if (!$mixDesign) {
            return '-';
        }

        $name = $mixDesign->design_name 
            ?? $mixDesign->name 
            ?? $mixDesign->title 
            ?? $mixDesign->design_code 
            ?? $mixDesign->code 
            ?? $mixDesign->design_type
            ?? $mixDesign->concrete_grade?->name 
            ?? $mixDesign->concreteGrade?->name;

        if (empty($name) && !empty($mixDesign->concrete_grade_id)) {
            $name = ConcreteGrade::find($mixDesign->concrete_grade_id)?->name;
        }

        return $name ?? $mixDesign->grade ?? '-';
    }

    public static function resolveConcreteGrade($mixDesign, ?string $fallback = null): string
    {
        if ($mixDesign) {
            $grade = $mixDesign->concrete_grade?->name 
                ?? $mixDesign->concreteGrade?->name 
                ?? $mixDesign->grade 
                ?? $mixDesign->design_type;
                
            if (!empty($grade) && $grade !== '-') {
                return (string) $grade;
            }

            if (!empty($mixDesign->design_name)) {
                if (preg_match('/^(M\s*\d+(?:\.\d+)?|PQC|DLC|GMM|WMM|CTB|FSG)\b/i', trim($mixDesign->design_name), $matches)) {
                    return strtoupper(str_replace(' ', '', $matches[1]));
                }
            }
        }

        if (!empty($fallback)) {
            if (preg_match('/^(M\s*\d+(?:\.\d+)?|PQC|DLC|GMM|WMM|CTB|FSG)\b/i', trim($fallback), $matches)) {
                return strtoupper(str_replace(' ', '', $matches[1]));
            }
            return $fallback;
        }

        return '-';
    }

    public static function formatMixDesignDescription(?string $baseDesc, $mixDesign): string
    {
        $description = $baseDesc ?? '';
        if ($mixDesign && $mixDesign->items && $mixDesign->items->count() > 0) {
            $materials = $mixDesign->items->map(function ($mdItem) {
                $prodName = $mdItem->product->title ?? $mdItem->product?->title ?? 'Unknown';
                $qty = (float) $mdItem->actual_quantity;
                $unit = $mdItem->uom->unit_code ?? $mdItem->uom?->unit_code ?? '';
                $formattedQty = $qty == floor($qty) ? (int) $qty : number_format($qty, 2);
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
                $qty = (float) $mdItem->actual_quantity;
                $unit = $mdItem->uom->unit_code ?? $mdItem->uom?->unit_code ?? '';
                $formattedQty = ($qty == floor($qty)) ? (int) $qty : number_format($qty, 2);
                $materials[] = [
                    'name' => $prodName,
                    'qty'  => $formattedQty,
                    'uom'  => $unit,
                ];
            }
        }
        return $materials;
    }

    public static function resolvePumpTypeLabel($concretePump = null, $rawType = null): string
    {
        if ($concretePump) {
            if (is_object($concretePump)) {
                return $concretePump->registration ?: ($concretePump->name ?? 'Dumping');
            }
            $rawType = (string) $concretePump;
        }

        if (empty($rawType)) {
            return '-';
        }

        if (is_numeric($rawType)) {
            $pumpMachine = Machine::find($rawType);
            return $pumpMachine?->registration ?? (string) $rawType;
        }

        return match (strtolower((string) $rawType)) {
            'line_pump'                       => 'Line Pump',
            'boom_pump'                       => 'Boom Pump',
            'static_pump', 'stationary_pump' => 'Stationary / Static Pump',
            'manual'                          => 'Manual',
            'dumping'                         => 'Dumping',
            default                           => ucwords(str_replace('_', ' ', (string) $rawType)),
        };
    }

    // =========================================================================
    //  5. TAX & FINANCIAL CALCULATIONS
    // =========================================================================

    public static function isIntraState(?string $plantGstin, ?string $partnerGstin): bool
    {
        $plantState   = strlen($plantGstin ?? '') >= 2 ? substr($plantGstin, 0, 2) : '33';
        $partnerState = strlen($partnerGstin ?? '') >= 2 ? substr($partnerGstin, 0, 2) : '';
        return !(strlen($partnerState) >= 2 && $partnerState !== $plantState);
    }

    public static function resolveTaxDetails($taxModel, bool $isIntra, float $priceTax, float $subtotal): array
    {
        $taxRate  = $taxModel ? (float) $taxModel->tax_rate : 0.0;
        $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
        $taxName  = $taxModel ? ($taxModel->tax_name ?? '') : '';

        if ($taxRate <= 0 && $priceTax > 0 && $subtotal > 0) {
            $taxRate  = round(($priceTax / $subtotal) * 100, 2);
            $taxGroup = $isIntra ? 'GST' : 'IGST';
            $taxName  = $taxGroup . ' ' . ($taxRate == floor($taxRate) ? (int) $taxRate : $taxRate) . '%';
        }

        return [
            'rate'  => $taxRate,
            'group' => $taxGroup,
            'name'  => $taxName,
        ];
    }

    public static function formatTaxLines(array $taxLinesMap): array
    {
        return collect($taxLinesMap)
            ->map(fn ($amt, $lbl) => ['label' => $lbl, 'amount' => (float) $amt])
            ->values()
            ->toArray();
    }

    public static function compileTaxLines($items, bool $isIntra, string $taxField, $subtotalFieldOrFn): array
    {
        $taxLines = [];
        foreach ($items as $item) {
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float) $taxModel->tax_rate : 0.0;
            $taxGroup = $taxModel ? ($taxModel->tax_group ?? '') : '';
            $priceTax = (float) ($item->{$taxField} ?? 0);
            
            $subtotal = is_callable($subtotalFieldOrFn)
                ? (float) $subtotalFieldOrFn($item)
                : (float) ($item->{$subtotalFieldOrFn} ?? 0);

            if ($priceTax <= 0 && !$taxModel) {
                continue;
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

        return self::formatTaxLines($taxLines);
    }

    public static function calculateLineItemTotals(
        float $quantity,
        float $rate,
        float $pumpRate = 0.0,
        float $taxRate = 0.0,
        bool $isTaxInclusive = false,
        float $discount = 0.0,
        bool $pumpChargeWithTax = true
    ): array {
        $qty        = (float) $quantity;
        $unitRate   = (float) $rate;
        $pumpCharge = (float) $pumpRate;
        $tRate      = (float) $taxRate;
        $disc       = (float) $discount;

        if ($isTaxInclusive) {
            $grossMaterial   = $qty * $unitRate;
            $materialUntaxed = $tRate > 0 ? (($grossMaterial * 100) / (100 + $tRate)) : $grossMaterial;
            $materialTax     = $grossMaterial - $materialUntaxed;
        } else {
            $materialUntaxed = $qty * $unitRate;
            $materialTax     = ($materialUntaxed * $tRate) / 100;
        }

        if ($pumpChargeWithTax) {
            if ($isTaxInclusive) {
                $pumpUntaxed = $tRate > 0 ? (($pumpCharge * 100) / (100 + $tRate)) : $pumpCharge;
                $pumpTax     = $pumpCharge - $pumpUntaxed;
            } else {
                $pumpUntaxed = $pumpCharge;
                $pumpTax     = ($pumpUntaxed * $tRate) / 100;
            }
        } else {
            $pumpUntaxed = $pumpCharge;
            $pumpTax     = 0.0;
        }

        $untaxedAmount    = $materialUntaxed + $pumpUntaxed;
        $taxAmount        = $materialTax + $pumpTax;
        $materialTotal    = $materialUntaxed + $materialTax - $disc;
        $amountTotal      = $untaxedAmount + $taxAmount - $disc;
        $displayUnitPrice = $qty > 0 ? ($materialUntaxed / $qty) : $unitRate;

        return [
            'materialUntaxed'  => (float) $materialUntaxed,
            'materialTax'      => (float) $materialTax,
            'materialTotal'    => (float) $materialTotal,
            'pumpCharge'       => (float) $pumpCharge,
            'untaxedAmount'    => (float) $untaxedAmount,
            'taxAmount'        => (float) $taxAmount,
            'amountTotal'      => (float) $amountTotal,
            'displayUnitPrice' => (float) $displayUnitPrice,
        ];
    }

    public static function calculateGrandTotal(
        float $subTotal,
        float $pumpCharge = 0.0,
        float $discount = 0.0,
        float $hireCharge = 0.0,
        float $passAmount = 0.0,
        float $taxAmount = 0.0,
        float $shipping = 0.0,
        float $adjustment = 0.0,
        float $roundOff = 0.0
    ): float {
        return (float) (($subTotal + $pumpCharge + $hireCharge + $passAmount + $taxAmount + $shipping + $adjustment + $roundOff) - $discount);
    }

    public static function resolveTermsCondition(array $settings, $orderType, int $plantId, ?string $fallbackTerms = null): string
    {
        if (empty($settings['pdf']['terms'])) return '';
        if (!empty($settings['pdf']['terms_text'])) return (string) $settings['pdf']['terms_text'];
        $orderTypes = is_array($orderType) ? $orderType : [$orderType];
        foreach ($orderTypes as $type) {
            $tc = TermsCondition::where('plant_id', $plantId)->where('order_type', $type)->where('status', 'active')->first();
            if ($tc) return $tc->terms_condition;
        }
        foreach ($orderTypes as $type) {
            $tc = TermsCondition::where('plant_id', 0)->where('order_type', $type)->where('status', 'active')->first();
            if ($tc) return $tc->terms_condition;
        }
        return $fallbackTerms ?? '';
    }

    // =========================================================================
    //  6. NUMBER TO WORDS (INDIAN SYSTEM)
    // =========================================================================

    private static array $ones = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];
    private static array $tens = [
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',
    ];

    private static function convertBelowCrore(int $num): string
    {
        if ($num === 0) return '';
        if ($num < 20) return self::$ones[$num];
        if ($num < 100) {
            $ten = (int) (floor($num / 10) * 10);
            $unit = $num % 10;
            return trim(self::$tens[$ten] . ($unit ? ' ' . self::$ones[$unit] : ''));
        }
        if ($num < 1000) {
            $h = intdiv($num, 100);
            $rem = $num % 100;
            return trim(self::$ones[$h] . ' Hundred' . ($rem ? ' ' . self::convertBelowCrore($rem) : ''));
        }
        if ($num < 100000) {
            $th = intdiv($num, 1000);
            $rem = $num % 1000;
            return trim(self::convertBelowCrore($th) . ' Thousand' . ($rem ? ' ' . self::convertBelowCrore($rem) : ''));
        }
        $lakh = intdiv($num, 100000);
        $rem = $num % 100000;
        return trim(self::convertBelowCrore($lakh) . ' Lakh' . ($rem ? ' ' . self::convertBelowCrore($rem) : ''));
    }

    private static function convertIndianTillCrore(int $num): string
    {
        if ($num === 0) return '';
        if ($num < 10000000) {
            return self::convertBelowCrore($num);
        }
        $croreCount = intdiv($num, 10000000);
        $rem = $num % 10000000;

        $croreWords = self::convertBelowCrore($croreCount);
        if ($croreCount >= 10000000) {
            $croreWords = self::convertIndianTillCrore($croreCount);
            $croreWords = preg_replace('/\s+Crore.*$/', '', $croreWords);
        }

        $result = trim($croreWords . ' Crore');
        if ($rem > 0) {
            $result .= ' ' . self::convertBelowCrore($rem);
        }
        return trim($result);
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

    // =========================================================================
    //  7. QR CODE RESOLVER
    // =========================================================================

    public static function formatQrCodeUrl(?string $qrCode, ?string $irn = null, ?string $upiQrPath = null, ?string $defaultPath = null): string
    {
        if (!empty($qrCode)) {
            if (str_starts_with($qrCode, 'data:image') || str_starts_with($qrCode, 'http://') || str_starts_with($qrCode, 'https://')) {
                return $qrCode;
            }

            $cleanPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $qrCode), '/');
            if (preg_match('/\.(png|jpe?g|svg|webp)$/i', $cleanPath) && !str_contains($cleanPath, '{') && !str_contains($cleanPath, '}')) {
                return asset('storage/' . $cleanPath);
            }

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

    // =========================================================================
    //  8. MODULE DOCUMENT FORMATTERS
    // =========================================================================

    public static function fromPurchaseOrder($order): array
    {
        $order->loadMissing(['items.product', 'items.uom', 'items.tax', 'vendor', 'plant', 'plant.entity', 'plant.addresses', 'currency']);
        $data = self::base();
        $templateKey       = self::resolveTemplateKey('purchase_orders', $order->plant_id);
        $data['settings']  = self::getCustomSettings($order->plant_id, 'purchase_orders', $templateKey);
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'PURCHASE ORDER';
        $data['doc_no']    = strtoupper((string) ($order->ref_no ?? ''));
        $data['doc_date']  = $order->date_order?->format('d/m/Y') ?? 'N/A';
        $data['due_date']  = $order->due_date?->format('d/m/Y') ?? 'N/A';
        $data['delivery_date'] = $order->date_planned?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($order->state ?? 'DRAFT');

        $data['company'] = self::formatCompany($order->plant);
        $data['bill_to'] = self::formatPartner($order->vendor);
        $data['ship_to'] = [
            'name'    => $data['company']['name'],
            'address' => $data['company']['address'],
            'city'    => $data['company']['city'],
            'state'   => $data['company']['state'],
            'pin'     => $data['company']['pin'],
        ];

        $isIntra       = self::isIntraState($order->plant->gstin ?? '', $order->vendor->gstin ?? '');
        $data['items'] = $order->items->map(function ($item, $idx) use ($isIntra) {
            $taxDetails = self::resolveTaxDetails($item->tax, $isIntra, (float) $item->price_tax, (float) $item->price_subtotal);
            return [
                'no'           => $idx + 1,
                'name'         => $item->product->title ?? '',
                'description'  => $item->description ?? '',
                'hsn'          => $item->product->hsn_code ?? '-',
                'qty'          => (float) $item->product_quantity,
                'received_qty' => (float) ($item->received_quantity ?? 0),
                'unit'         => $item->uom->unit_code ?? '',
                'unit_price'   => (float) $item->unit_price,
                'tax_name'     => $taxDetails['name'] ?: '-',
                'tax_rate'     => $taxDetails['rate'],
                'tax_group'    => $taxDetails['group'],
                'tax_amount'   => (float) $item->price_tax,
                'total'        => (float) $item->price_total,
            ];
        })->toArray();

        $data['totals'] = [
            'sub_total'   => (float) $order->amount_untaxed,
            'discount'    => (float) ($order->discount_amount ?? 0),
            'pump_charge' => 0.00,
            'hire_charge' => (float) ($order->shipping_charges ?? 0),
            'pass_amount' => 0.00,
            'tax_amount'  => (float) ($order->amount_tax ?? 0),
            'tax_lines'   => self::compileTaxLines($order->items, $isIntra, 'price_tax', 'price_subtotal'),
            'adjustment'  => (float) ($order->adjustment ?? 0),
            'round_off'   => (float) ($order->round_off ?? 0),
            'grand_total' => (float) $order->amount_total,
        ];
        $data['meta']   = [
            'po_number'      => $order->po_number ?? $order->ref_no,
            'project_name'   => $order->plant->name,
            'currency_code'  => $order->currency->currency_code ?? 'INR',
            'currency_symbol' => $order->currency->currency_symbol ?? '₹',
            'notes'          => $order->notes ?? '',
            'terms_text'     => self::resolveTermsCondition($data['settings'], 'Purchase Order', $order->plant_id, $order->terms_conditions ?? ''),
            'total_words'    => self::numberToWords($order->amount_total, $order->currency->currency_code ?? 'INR'),
            'site_incharge'  => $order->plant->site_incharge ?? '',
            'contact_no'     => $order->plant->contact_no ?? '',
            'receipt_status' => (int) $order->receipt_status,
        ];
        return $data;
    }

    public static function fromInvoice($invoice): array
    {
        $isPurchaseBill = strtolower((string)$invoice->invoice_type) === 'bill';
        $invoice->loadMissing([
            'plant', 'plant.entity', 'plant.addresses', 'partner', 'partner.addresses', 'partner.contacts.addresses',
            'items.tax', 'items.uom', 'items.itemTaxes', 'orderTaxes'
        ]);

        if (!$isPurchaseBill) {
            $invoice->loadMissing(['items.mixDesign.concreteGrade', 'items.mixDesign.concrete_grade', 'items.mixDesign.items.product', 'items.mixDesign.items.uom']);
        }

        $data               = self::base();
        $data['id']         = $invoice->id;
        $data['invoice_id'] = $invoice->id;
        $data['invoice']    = $invoice;
        $data['settings']   = self::getCustomSettings($invoice->plant_id, $isPurchaseBill ? 'purchase_bills' : 'invoices');
        $data['is_purchase_bill'] = $isPurchaseBill;
        if ($isPurchaseBill) {
            foreach (['show_pump_charges', 'pump_rates', 'show_recipe_details', 'show_carrier_driver', 'show_customer_ref', 'show_einvoice_details'] as $setting) {
                $data['settings']['pdf'][$setting] = false;
            }
            $data['settings']['pdf']['labels']['bill_to'] = 'Supplier';
        }

        $defaultTitle = $invoice->invoice_type === 'bill' ? 'PURCHASE BILL' : 'TAX INVOICE';
        $docTitle     = $data['settings']['pdf']['labels']['invoice_title'] ?? $defaultTitle;
        if (!empty($invoice->invoice_label)) {
            if (strtolower($invoice->invoice_label) === 'manual') $docTitle = 'MANUAL BILLING';
            elseif (strtolower($invoice->invoice_label) === 'dispatch') $docTitle = $defaultTitle;
            else $docTitle = strtoupper($invoice->invoice_label);
        }

        $data['doc_title'] = $docTitle;
        $data['doc_no']    = strtoupper((string) ($invoice->prefix . $invoice->invoice_number));
        $data['doc_date']  = $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $data['due_date']  = $invoice->due_date?->format('d/m/Y') ?? 'N/A';
        $data['state']     = strtoupper($invoice->status ?? 'DRAFT');
        $data['company']   = self::formatCompany($invoice->plant);

        $dispatch = $isPurchaseBill ? null : Dispatch::whereHas('status', fn ($q) => $q->where('invoice_id', $invoice->id))->with([
            'salesOrder.customer', 'salesOrder.site', 'salesOrder.salesExecutive', 'salesOrder.mixDesign.concrete_grade', 'salesOrder.mixDesign', 'salesOrder.customerPO',
            'unloadSite', 'customer', 'customerPO.patron', 'customerPO.site',
            'concretePump', 'truck', 'transport', 'driver', 'mixDesign.concrete_grade', 'mixDesign', 'salesExecutive'
        ])->first();

        if (!$isPurchaseBill && !$dispatch && !empty($invoice->ref_id)) {
            $refIds     = array_filter(array_map('trim', explode(',', $invoice->ref_id)));
            $firstRefId = reset($refIds);
            if (is_numeric($firstRefId)) {
                $dispatch = Dispatch::with([
                    'salesOrder.customer', 'salesOrder.site', 'salesOrder.salesExecutive', 'salesOrder.mixDesign.concrete_grade', 'salesOrder.mixDesign', 'salesOrder.customerPO',
                    'unloadSite', 'customer', 'customerPO.patron', 'customerPO.site',
                    'concretePump', 'truck', 'transport', 'driver', 'mixDesign.concrete_grade', 'mixDesign', 'salesExecutive'
                ])->find($firstRefId);
            }
        }

        $partner = $invoice->partner;
        if ($dispatch && (!$partner || empty($partner->name))) {
            $partner = $dispatch->customer ?: ($dispatch->salesOrder?->customer ?: ($dispatch->customerPO?->patron ?: null));
        }

        $salesOrder = $dispatch?->salesOrder;
        $customerPO = $dispatch?->customerPO ?: $salesOrder?->customerPO;

        if ($partner) {
            $partner->loadMissing(['addresses', 'contacts.addresses']);
        }
        $data['bill_to'] = self::formatPartner($partner);

        $site = $dispatch ? ($dispatch->unloadSite ?: ($dispatch->salesOrder?->site ?: ($dispatch->customerPO?->site ?: null))) : null;
        if (!$site && $customerPO) $site = $customerPO->site;
        if (!$site && $salesOrder) $site = $salesOrder->site;
        $data['ship_to'] = self::formatShipTo($site, $data['bill_to']);

        $salesExec        = $dispatch?->salesExecutive ?: ($salesOrder?->salesExecutive ?: null);
        $salesPersonName  = self::formatSalesExecutiveName($salesExec);
        $pumpName         = self::resolvePumpTypeLabel($dispatch?->concretePump, $dispatch?->concrete_pump ?? $salesOrder?->concrete_pump);
        $mixDesignObj     = $dispatch?->mixDesign ?: ($salesOrder?->mixDesign ?: null);
        $designMixRef     = self::resolveMixDesignName($mixDesignObj);
        $carrierDriverText = self::formatCarrierDriverDetails(
            $dispatch?->transport?->name ?? ($invoice->plant?->name ?? ''),
            $dispatch?->truck?->registration ?? '',
            $dispatch?->driver
        );

        $isIntra             = self::isIntraState($invoice->plant->gstin ?? '', $partner?->gstin ?? '');
        $showPumpCharges     = !isset($data['settings']['pdf']['show_pump_charges']) || $data['settings']['pdf']['show_pump_charges'];
        $dispatchPumpCharge  = $dispatch ? (float) ($dispatch->pump_charges ?? 0.0) : 0.0;
        $pumpChargesTotal    = 0.0;
        $printItemNameFormat = self::getPrintItemNameFormat($data['settings'], $invoice->plant_id);

        $data['items'] = $invoice->items->map(function ($item, $idx) use (
            $isPurchaseBill, $isIntra, $showPumpCharges, $dispatch, $dispatchPumpCharge, $mixDesignObj, $printItemNameFormat, &$pumpChargesTotal
        ) {
            $taxModel     = $item->tax;
            $lineTaxAmount = (float) $item->line_tax_amount;

            if (!$taxModel && $item->relationLoaded('itemTaxes') && $item->itemTaxes->isNotEmpty()) {
                $splits       = $item->itemTaxes;
                $lineTaxAmount = (float) $splits->sum('amount');
                $totalRate    = (float) $splits->sum('rate');
                $firstSplit   = strtolower($splits->first()->name ?? '');
                $taxGroup     = str_contains($firstSplit, 'igst') ? 'IGST' : (str_contains($firstSplit, 'cgst') || str_contains($firstSplit, 'sgst') ? 'GST' : ($isIntra ? 'GST' : 'IGST'));
                $taxName      = $taxGroup . ' ' . ($totalRate == floor($totalRate) ? (int) $totalRate : $totalRate) . '%';
                $taxDetails   = ['rate' => $totalRate, 'group' => $taxGroup, 'name' => $taxName];
            } else {
                $taxDetails = self::resolveTaxDetails($taxModel, $isIntra, $lineTaxAmount, (float) $item->subtotal);
            }

            $operationType = '-';
            $pumpCharge    = 0.0;
            if ($showPumpCharges && $dispatch) {
                $operationType = self::resolvePumpTypeLabel($dispatch->concretePump, $dispatch->concrete_pump);
                $pumpCharge    = $dispatchPumpCharge;
            }

            $pumpChargesTotal += $dispatchPumpCharge;

            $itemSubtotal = (float) ($item->subtotal ?? ($item->quantity * $item->price_unit));
            $itemTotal    = (float) ($itemSubtotal + $lineTaxAmount);
            $taxInWords   = self::numberToWords($lineTaxAmount);
            $itemName     = $isPurchaseBill ? $item->item_name : self::resolvePrintedItemName($item->mixDesign ?? $mixDesignObj, $printItemNameFormat, $item->item_name);

            return [
                'no'               => $idx + 1,
                'name'             => $itemName,
                'grade'            => $itemName,
                'description'      => '',
                'hsn'              => $item->hsn_code ?? '-',
                'qty'              => (float) $item->quantity,
                'unit'             => $item->uom->unit_code ?? ($isPurchaseBill ? '-' : 'm³'),
                'unit_price'       => (float) $item->price_unit,
                'discount'         => (float) ($item->discount_amount ?? $item->discount ?? 0),
                'operation_type'   => $operationType,
                'pump_charge'      => $pumpCharge,
                'taxable_amount'   => $itemSubtotal,
                'tax_words'        => $taxInWords,
                'tax_name'         => $taxDetails['name'] ?: '-',
                'tax_rate'         => $taxDetails['rate'],
                'tax_group'        => $taxDetails['group'],
                'tax_amount'       => $lineTaxAmount,
                'total'            => $itemTotal,
                'recipe_materials' => $isPurchaseBill ? [] : self::resolveRecipeMaterials($item->mixDesign ?? $mixDesignObj),
            ];
        })->toArray();

        $taxLines    = $invoice->orderTaxes->map(fn ($ot) => ['label' => $ot->name, 'amount' => (float) $ot->amount])->toArray();
        $subtotalVal = (float) $invoice->subtotal;
        $discVal     = (float) ($invoice->discount_total ?? $invoice->global_discount ?? 0);
        if ($discVal == 0 && $invoice->items) {
            $discVal = (float) $invoice->items->sum('discount_amount');
        }
        $pumpVal     = (float) ($dispatch?->pump_charges ?? $pumpChargesTotal ?? 0);
        $hireVal     = (float) ($dispatch?->transport_expenses ?? 0);
        $passVal     = (float) ($dispatch?->pass_amount ?? 0);
        $taxVal      = (float) ($invoice->tax_amount ?? $invoice->amount_tax ?? 0);
        if ($taxVal == 0 && !empty($taxLines)) {
            $taxVal = (float) array_sum(array_column($taxLines, 'amount'));
        }
        $shippingVal = ($hireVal > 0) ? 0.0 : (float) ($invoice->shipping_charges ?? $invoice->shipping ?? 0);
        $adjVal      = (float) ($invoice->adjustment ?? 0);
        $roundVal    = (float) (($invoice->round_off != 0 ? $invoice->round_off : ($dispatch?->round_off ?? 0)));

        if ($subtotalVal <= 0 && !empty($data['items'])) {
            $subtotalVal = (float) array_sum(array_column($data['items'], 'taxable_amount'));
        }

        if ($taxVal <= 0 && !empty($taxLines)) {
            $taxVal = (float) array_sum(array_column($taxLines, 'amount'));
        } elseif ($taxVal <= 0 && !empty($data['items'])) {
            $taxVal = (float) array_sum(array_column($data['items'], 'tax_amount'));
        }

        $itemsTotalSum = !empty($data['items']) ? (float) array_sum(array_column($data['items'], 'total')) : 0.0;
        $calcTotal     = self::calculateGrandTotal($subtotalVal, $pumpVal, $discVal, $hireVal, $passVal, $taxVal, $shippingVal, $adjVal, $roundVal);
        $invTotal      = (float) ($invoice->total_amount ?? 0);

        if ($isPurchaseBill) {
            $grandTotalVal = $invTotal;
        } elseif ($invTotal > 0 && ($itemsTotalSum <= 0 || $invTotal >= ($itemsTotalSum - 1.0))) {
            $grandTotalVal = $invTotal;
        } elseif ($calcTotal > 0) {
            $grandTotalVal = $calcTotal;
        } elseif ($itemsTotalSum > 0) {
            $grandTotalVal = $itemsTotalSum;
        } elseif ($invTotal > 0) {
            $grandTotalVal = $invTotal;
        } else {
            $grandTotalVal = (float) ($dispatch?->load_total_amount ?? 0);
        }

        $data['totals'] = [
            'sub_total'   => $subtotalVal,
            'discount'    => $discVal,
            'pump_charge' => $pumpVal,
            'hire_charge' => $hireVal,
            'pass_amount' => $passVal,
            'tax_amount'  => $taxVal,
            'tax_lines'   => $taxLines,
            'shipping'    => $shippingVal,
            'adjustment'  => $adjVal,
            'round_off'   => $roundVal,
            'grand_total' => $grandTotalVal,
        ];

        $orderTypeForTerms = $invoice->invoice_type === 'bill' ? 'Purchase Bill' : [($invoice->invoice_label ?? 'Tax Invoice'), 'Tax Invoice'];
        $poNumber          = $customerPO?->customer_po_reference
            ?: ($customerPO?->reference
            ?: ($salesOrder?->customer_po_reference
            ?: ($salesOrder?->po_number
            ?: ($dispatch?->customer_po_reference
            ?: ($dispatch?->dispatch_reference
            ?: ($invoice->ref_title ?? ''))))));

        $testDummyQrPath = asset('storage/plants/demo-mining-corp/parker-llc-plant/upi_qr_1784092752.png');

        $data['meta'] = [
            'currency_code'          => 'INR',
            'currency_symbol'        => '₹',
            'notes'                  => $invoice->notes ?? '',
            'terms_text'             => self::resolveTermsCondition($data['settings'], $orderTypeForTerms, $invoice->plant_id, "1. Goods once sold will not be taken back.\n2. Interest @ 18% will be charged if not paid within due date.\n3. All disputes are subject to local jurisdiction."),
            'total_words'            => self::numberToWords($grandTotalVal, 'INR'),
            'po_number'              => $poNumber,
            'project_name'           => $invoice->ref_title ?? ($salesOrder?->site?->name ?? ''),
            'irn'                   => $invoice->einvoice_irn ?? '',
            'ack_no'                 => $invoice->einvoice_ack_no ?? '',
            'ack_date'               => $invoice->einvoice_ack_date?->format('d/m/Y') ?? '',
            'qr_code'                => self::formatQrCodeUrl($invoice->einvoice_qr_code, $invoice->einvoice_irn, $testDummyQrPath),
            'eway_bill_no'           => $invoice->eway_bill_no ?? '',
            'so_no'                  => $salesOrder ? ($salesOrder->full_number ?? $salesOrder->order_no) : ($dispatch?->salesOrder?->order_no ?? ''),
            'acc_no'                 => $partner?->account_number ?? $partner?->code ?? ($partner ? 'AC-' . sprintf('%04d', $partner->id) : ''),
            'sales_person'           => $salesPersonName,
            'pump'                   => $pumpName,
            'quality_incharge'       => $dispatch?->quality_incharge ?? $salesOrder?->quality_incharge ?? $invoice->plant?->quality_incharge ?? '-',
            'design_mix_ref'         => $designMixRef,
            'carrier_driver'         => $carrierDriverText,
            'sales_executive_name'    => $salesPersonName,
            'sales_executive_mobile'  => self::formatSalesExecutiveMobile($salesExec),
        ];

        return $data;
    }

    public static function fromQuotation($quotation, ?array $customSettings = null, bool $isPriceList = false): array
    {
        $statusLabels = [0 => 'DRAFT', 1 => 'SENT', 2 => 'ACCEPTED', 3 => 'REJECTED'];
        $state        = $statusLabels[$quotation->status] ?? 'DRAFT';

        return self::fromQuotationOrCustomerPO(
            $quotation, 'quotations', 'QUOTATION',
            $quotation->reference ?? $quotation->id,
            $quotation->quote_date?->format('d/m/Y'),
            $quotation->validity_date?->format('d/m/Y'),
            $state, $customSettings, $isPriceList
        );
    }

    public static function fromCustomerPO($customerPO, ?array $customSettings = null): array
    {
        $statusLabels = [0 => 'DRAFT', 1 => 'CONFIRMED', 2 => 'COMPLETED'];
        $state        = $statusLabels[$customerPO->status] ?? 'DRAFT';

        return self::fromQuotationOrCustomerPO(
            $customerPO, 'customer_pos', 'CUSTOMER PO',
            $customerPO->reference ?? $customerPO->id,
            $customerPO->order_date?->format('d/m/Y'),
            $customerPO->due_date?->format('d/m/Y'),
            $state, $customSettings, false
        );
    }

    private static function fromQuotationOrCustomerPO(
        $model,
        string $module,
        string $defaultTitle,
        string $docNo,
        ?string $docDate,
        ?string $dueDate,
        string $state,
        ?array $customSettings = null,
        bool $isPriceList = false
    ): array {
        $model->loadMissing([
            'items.mixDesign', 'items.mixDesign.concrete_grade', 'items.mixDesign.concreteGrade',
            'items.mixDesign.items.product', 'items.mixDesign.items.uom', 'items.mixDesign.unit',
            'items.uom', 'items.tax', 'patron', 'patron.addresses', 'patron.contacts.addresses',
            'site', 'plant', 'plant.entity', 'plant.addresses', 'tax', 'salesExecutive',
        ]);

        $data             = self::base();
        $data['settings'] = $customSettings ?? self::getCustomSettings($model->plant_id, $module);

        if (!$customSettings && $module === 'customer_pos' && empty(CustomSetting::getForModule($model->plant_id, 'customer_pos'))) {
            $data['settings'] = self::getCustomSettings($model->plant_id, 'quotations');
        }

        if ($isPriceList) {
            $data['doc_title'] = 'PRICE LIST';
            $data['settings']['pdf']['amount']      = true;
            $data['settings']['pdf']['show_totals'] = false;
        } else {
            $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? $defaultTitle;
        }

        $data['doc_no']   = strtoupper((string) $docNo);
        $data['doc_date'] = $docDate ?? now()->format('d/m/Y');
        $data['due_date'] = $dueDate ?? 'N/A';
        $data['state']    = $state;

        $isTaxInclusive           = (bool) ($model->is_tax_inclusive ?? false);
        $data['is_tax_inclusive'] = $isTaxInclusive;

        $data['company'] = self::formatCompany($model->plant);
        $data['bill_to'] = self::formatPartner($model->patron);
        $data['ship_to'] = self::formatShipTo($model->site, $data['bill_to']);
        $isIntra         = self::isIntraState($model->plant->gstin ?? '', $model->patron->gstin ?? '');

        $isBoom   = false;
        $isManual = false;
        if ($model->concretePump) {
            $vehicleType  = strtolower($model->concretePump->vehicle_type ?? '');
            $registration = strtolower($model->concretePump->registration ?? '');
            $isBoom       = str_contains($vehicleType, 'boom') || str_contains($registration, 'boom');
            $isManual     = str_contains($vehicleType, 'manual') || str_contains($registration, 'manual');
        }

        $selectedRate = $model->concrete_pump
            ? ($isManual ? (float) ($model->manual_rate ?? 0) : ($isBoom ? (float) ($model->boom_pump_rate ?? 0) : (float) ($model->pump_rate ?? 0)))
            : (float) ($model->manual_rate ?? 0);

        $printItemNameFormat = self::getPrintItemNameFormat($data['settings'], $model->plant_id);
        $showPumpCharges     = !isset($data['settings']['pdf']['show_pump_charges']) || $data['settings']['pdf']['show_pump_charges'];

        $subtotalAmt   = 0.0;
        $totalTaxAmt   = 0.0;
        $grandTotalAmt = 0.0;

        $data['items'] = $model->items->map(function ($item, $idx) use (
            $model, $isIntra, $isTaxInclusive, $selectedRate, $printItemNameFormat, $showPumpCharges,
            &$subtotalAmt, &$totalTaxAmt, &$grandTotalAmt
        ) {
            $actualItemPumpRate = (float) ($item->pump_rate ?? 0);
            $pumpTypeLabel      = '-';

            if ($item->concrete_pump || $model->concrete_pump) {
                if ($actualItemPumpRate === 0.0) {
                    $actualItemPumpRate = $selectedRate;
                }
                $pumpVal       = $item->concrete_pump ?? $model->concrete_pump;
                $pumpTypeLabel = self::resolvePumpTypeLabel(null, (string) $pumpVal);
            }

            $rate     = (float) $item->rate;
            $qty      = (float) $item->quantity;
            $taxModel = $item->tax;
            $taxRate  = $taxModel ? (float) ($taxModel->tax_rate ?? $taxModel->rate ?? 0) : 0.0;
            $discount = (float) ($item->discount_amount ?? $item->discount ?? 0);

            $calcs       = self::calculateLineItemTotals($qty, $rate, $actualItemPumpRate, $taxRate, $isTaxInclusive, $discount);
            $lineTotal   = $calcs['amountTotal'];
            $lineTax     = $calcs['taxAmount'];
            $lineUntaxed = $calcs['untaxedAmount'];

            $subtotalAmt   += $lineUntaxed;
            $totalTaxAmt   += $lineTax;
            $grandTotalAmt += $lineTotal;

            $taxDetails        = self::resolveTaxDetails($taxModel, $isIntra, $lineTax, $lineUntaxed);
            $displayUnitPrice  = $calcs['displayUnitPrice'];
            $displayPumpCharge = $showPumpCharges ? $actualItemPumpRate : 0.0;
            if (!$showPumpCharges) {
                $pumpTypeLabel = '-';
            }

            $itemDescription = self::formatMixDesignDescription($item->description, $item->mixDesign);
            $printedItemName = self::resolvePrintedItemName($item->mixDesign, $printItemNameFormat, $item->description);

            return [
                'no'               => $idx + 1,
                'name'             => $printedItemName,
                'grade'            => $printedItemName,
                'description'      => $itemDescription,
                'hsn'              => $item->mixDesign->hsn_code ?? '-',
                'qty'              => $qty,
                'received_qty'     => 0,
                'unit'             => $item->mixDesign->unit->unit_code ?? 'm³',
                'unit_price'       => $displayUnitPrice,
                'operation_type'   => $pumpTypeLabel,
                'pump_charge'      => $displayPumpCharge,
                'tax_name'         => $taxDetails['name'] ?: '-',
                'tax_rate'         => $taxDetails['rate'],
                'tax_group'        => $taxDetails['group'],
                'tax_amount'       => (float) $lineTax,
                'total'            => (float) $lineTotal,
                'pump_rates'       => [],
                'recipe_materials' => self::resolveRecipeMaterials($item->mixDesign),
            ];
        })->toArray();

        $taxLines = [];
        foreach ($data['items'] as $item) {
            $taxAmt = $item['tax_amount'];
            if ($taxAmt > 0) {
                $group = strtoupper(trim($item['tax_group'] ?: ($isIntra ? 'GST' : 'IGST')));
                if ($group === 'GST') {
                    $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($taxAmt / 2);
                    $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($taxAmt / 2);
                } else {
                    $taxLines[$group] = ($taxLines[$group] ?? 0) + $taxAmt;
                }
            }
        }
        $formattedTaxLines = self::formatTaxLines($taxLines);

        $adjustment      = (float) ($model->adjustment ?? 0);
        $finalGrandTotal = $grandTotalAmt + $adjustment;
        $ratesTableHtml  = '';

        $data['totals'] = [
            'sub_total'          => (float) $subtotalAmt,
            'discount'           => 0,
            'tax_lines'          => $formattedTaxLines,
            'shipping'           => 0,
            'adjustment'         => $adjustment,
            'round_off'          => 0,
            'pump_rate'          => 0.0,
            'pump_charges_total' => 0.0,
            'grand_total'        => (float) $finalGrandTotal,
            'rates_table_html'   => $ratesTableHtml,
        ];

        $termsText = self::resolveTermsCondition(
            $data['settings'],
            $module === 'customer_pos' ? 'Customer PO' : 'Quotation',
            $model->plant_id,
            $model->terms_conditions ?? ''
        );

        $salesExec = $model->salesExecutive;

        $data['meta'] = [
            'currency_code'          => 'INR',
            'currency_symbol'        => '₹',
            'notes'                  => $model->notes ?? '',
            'terms_text'             => $ratesTableHtml . $termsText,
            'total_words'            => self::numberToWords($finalGrandTotal, 'INR'),
            'sales_executive_name'   => self::formatSalesExecutiveName($salesExec),
            'sales_executive_mobile' => self::formatSalesExecutiveMobile($salesExec),
        ];

        return $data;
    }

    public static function fromSalesOrder($salesOrder): array
    {
        $salesOrder->loadMissing([
            'customer', 'customer.addresses', 'customer.contacts.addresses', 'site', 'plant', 'plant.entity', 'plant.addresses',
            'mixDesign', 'mixDesign.concrete_grade', 'mixDesign.concreteGrade', 'mixDesign.unit', 'mixDesign.items.product', 'mixDesign.items.uom',
            'tax', 'customerPO.items.mixDesign', 'customerPO.items.mixDesign.concrete_grade', 'customerPO.items.mixDesign.concreteGrade',
            'customerPO.items.tax', 'customerPO.quotation.items.mixDesign', 'customerPO.quotation.items.mixDesign.concrete_grade',
            'customerPO.quotation.items.mixDesign.concreteGrade', 'customerPO.quotation.items.tax', 'customerPO.quotation.items.mixDesign.items.product',
            'customerPO.quotation.items.mixDesign.items.uom'
        ]);

        $data             = self::base();
        $data['settings'] = self::getCustomSettings($salesOrder->plant_id, 'sales_orders') ?: self::getCustomSettings($salesOrder->plant_id, 'quotations');
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'SALES ORDER';
        $data['doc_no']    = strtoupper((string) (($salesOrder->prefix ?? '') . ($salesOrder->order_no ?? $salesOrder->id)));
        $data['doc_date']  = $salesOrder->created_at ? $salesOrder->created_at->format('d/m/Y') : now()->format('d/m/Y');
        $data['due_date']  = $salesOrder->scheduled_end ? Carbon::parse($salesOrder->scheduled_end)->format('d/m/Y') : '';

        $statusMap     = [1 => 'SCHEDULED', 2 => 'IN PROGRESS', 3 => 'COMPLETED', 4 => 'CANCELLED'];
        $data['state'] = $statusMap[$salesOrder->status] ?? 'DRAFT';

        $data['company'] = self::formatCompany($salesOrder->plant);
        $data['bill_to'] = self::formatPartner($salesOrder->customer);
        $data['ship_to'] = self::formatShipTo($salesOrder->site, $data['bill_to']);

        $isTaxInclusive      = (bool) ($salesOrder->is_tax_inclusive ?? ($salesOrder->customerPO?->is_tax_inclusive ?? false));
        $isIntra             = self::isIntraState($salesOrder->plant->gstin ?? '', $salesOrder->customer?->gstin ?? '');
        $quotationItems      = $salesOrder->customerPO?->quotation?->items ?? collect();
        $printItemNameFormat = self::getPrintItemNameFormat($data['settings'], $salesOrder->plant_id);

        if ($quotationItems->isNotEmpty()) {
            $data['items'] = $quotationItems->map(function ($item, $idx) use ($isIntra, $isTaxInclusive, $salesOrder, $printItemNameFormat) {
                $subtotal    = (float) ($item->untaxed_amount ?? ($item->quantity * $item->rate));
                $taxDetails  = self::resolveTaxDetails($item->tax, $isIntra, (float) $item->tax_amount, $subtotal);
                $description = self::formatMixDesignDescription($item->description, $item->mixDesign);
                $unitPrice   = $isTaxInclusive ? (float) ($item->quantity > 0 ? ($subtotal / $item->quantity) : $item->rate) : (float) $item->rate;
                $gradeValue  = self::resolvePrintedItemName($item->mixDesign, $printItemNameFormat);

                return [
                    'no'               => $idx + 1,
                    'name'             => $gradeValue,
                    'grade'            => $gradeValue,
                    'description'      => $description,
                    'hsn'              => $item->mixDesign?->hsn_code ?? '-',
                    'qty'              => (float) $item->quantity,
                    'received_qty'     => (float) ($salesOrder->produced_qty ?? 0),
                    'unit'             => $item->mixDesign?->unit?->unit_code ?? 'm³',
                    'unit_price'       => $unitPrice,
                    'tax_name'         => $taxDetails['name'] ?: '-',
                    'tax_rate'         => $taxDetails['rate'],
                    'tax_group'        => $taxDetails['group'],
                    'tax_amount'       => (float) $item->tax_amount,
                    'total'            => (float) ($item->amount_total ?? ($item->quantity * $item->rate)),
                    'recipe_materials' => self::resolveRecipeMaterials($item->mixDesign),
                ];
            })->toArray();
        } else {
            $mixDesign = $salesOrder->mixDesign;
            $qty       = (float) ($salesOrder->total_qty ?? 0);
            $poItem    = $salesOrder->customerPO ? $salesOrder->customerPO->items->where('mix_design_id', $salesOrder->mix_design_id)->first() : null;
            $rate      = (float) ($salesOrder->rate ?? ($poItem ? $poItem->rate : ($mixDesign?->rate_per_qty ?? 0)));
            $taxModel  = $salesOrder->tax ?? $poItem?->tax;
            $taxRate   = 0.0;
            $taxGroup  = '';
            $taxName   = '-';
            $priceTax  = 0.0;

            if ($taxModel) {
                $taxRate  = (float) ($taxModel->tax_rate ?? $taxModel->rate ?? 0);
                $taxGroup = $taxModel->tax_group ?? '';
                $taxName  = $taxModel->tax_name ?? '';
            }

            $calcs      = self::calculateLineItemTotals($qty, $rate, 0.0, $taxRate, $isTaxInclusive);
            $untaxedAmt = $calcs['materialUntaxed'];
            $priceTax   = $calcs['materialTax'];
            $total      = $calcs['materialTotal'];
            $unitPrice  = $calcs['displayUnitPrice'];

            if ($taxRate <= 0 && $poItem) {
                $itemTaxAmount     = (float) $poItem->tax_amount;
                $itemUntaxedAmount = (float) ($poItem->untaxed_amount ?? ($poItem->quantity * $poItem->rate));
                if ($itemTaxAmount > 0 && $itemUntaxedAmount > 0) {
                    $taxRate  = round(($itemTaxAmount / $itemUntaxedAmount) * 100, 2);
                    $taxGroup = $isIntra ? 'GST' : 'IGST';
                    $taxName  = $taxGroup . ' ' . ($taxRate == floor($taxRate) ? (int) $taxRate : $taxRate) . '%';
                    if ($isTaxInclusive) {
                        $untaxedAmt = $total / (1 + ($taxRate / 100));
                        $priceTax   = $total - $untaxedAmt;
                        $unitPrice  = $qty > 0 ? ($untaxedAmt / $qty) : $rate;
                    } else {
                        $priceTax = $untaxedAmt * ($taxRate / 100);
                        $total    = $untaxedAmt + $priceTax;
                    }
                }
            }

            $taxDetails = self::resolveTaxDetails($taxModel, $isIntra, $priceTax, $untaxedAmt);
            if ($taxRate > 0 && empty($taxDetails['name'])) {
                $taxDetails['name'] = ($taxGroup ?: ($isIntra ? 'GST' : 'IGST')) . ' ' . ($taxRate == floor($taxRate) ? (int) $taxRate : $taxRate) . '%';
            }

            $description         = self::formatMixDesignDescription('', $mixDesign);
            $printItemNameFormat = self::getPrintItemNameFormat($data['settings']);
            $grade               = self::resolvePrintedItemName($mixDesign, $printItemNameFormat);

            $data['items'] = $mixDesign ? [[
                'no'               => 1,
                'name'             => $grade,
                'grade'            => $grade,
                'description'      => $description,
                'hsn'              => $mixDesign->hsn_code ?? '-',
                'qty'              => $qty,
                'received_qty'     => (float) ($salesOrder->produced_qty ?? 0),
                'unit'             => $mixDesign->unit?->unit_code ?? 'm³',
                'unit_price'       => $unitPrice,
                'tax_name'         => $taxDetails['name'] ?: ($taxName ?: '-'),
                'tax_rate'         => $taxDetails['rate'] ?: $taxRate,
                'tax_group'        => $taxDetails['group'] ?: $taxGroup,
                'tax_amount'       => $priceTax,
                'total'            => $total,
                'recipe_materials' => self::resolveRecipeMaterials($mixDesign),
            ]] : [];
        }

        $concretePump    = $salesOrder->concrete_pump;
        $pumpRate        = (float) ($salesOrder->pump_rate ?? 0);
        $showPumpCharges = !isset($data['settings']['pdf']['show_pump_charges']) || $data['settings']['pdf']['show_pump_charges'];
        $pumpTypeLabel   = self::resolvePumpTypeLabel(null, (string) $concretePump);

        $itemsCount = count($data['items']);
        if ($itemsCount > 0) {
            foreach ($data['items'] as $i => &$item) {
                $item['operation_type'] = $showPumpCharges ? $pumpTypeLabel : '-';
                $item['pump_charge']    = ($showPumpCharges && $i === 0) ? $pumpRate : 0.0;
            }
            unset($item);
        }

        $showPumpRate    = (!isset($data['settings']['pdf']['pump_rates']) || $data['settings']['pdf']['pump_rates']) && $pumpRate > 0;
        $pumpRateUntaxed = 0.0;
        $pumpRateTax     = 0.0;
        $pumpRateTotal   = 0.0;
        $taxGroup        = '';

        if ($showPumpRate) {
            $firstItem = collect($data['items'])->first();
            $taxRate   = 0.0;
            if ($firstItem) {
                $taxRate  = (float) ($firstItem['tax_rate'] ?? 0);
                $taxGroup = $firstItem['tax_group'] ?? '';
            }

            if ($isTaxInclusive) {
                $pumpRateTotal   = $pumpRate;
                $pumpRateTax     = $pumpRateTotal - ($pumpRateTotal / (1 + $taxRate / 100));
                $pumpRateUntaxed = $pumpRateTotal - $pumpRateTax;
            } else {
                $pumpRateUntaxed = $pumpRate;
                $pumpRateTax     = $pumpRateUntaxed * ($taxRate / 100);
                $pumpRateTotal   = $pumpRateUntaxed + $pumpRateTax;
            }
        }

        $subTotalVal   = 0.0;
        $grandTotalVal = 0.0;
        $taxLines      = [];

        foreach ($data['items'] as $item) {
            if ($isTaxInclusive) {
                $subTotalVal += ((float) $item['total'] - (float) $item['tax_amount']);
            } else {
                $subTotalVal += ((float) $item['qty'] * (float) $item['unit_price']);
            }
            $grandTotalVal += (float) $item['total'];

            $taxAmt = (float) $item['tax_amount'];
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

        if ($showPumpRate && $pumpRateTax > 0) {
            $g = strtoupper(trim($taxGroup ?: ($isIntra ? 'GST' : 'IGST')));
            if ($g === 'GST') {
                $taxLines['CGST'] = ($taxLines['CGST'] ?? 0) + ($pumpRateTax / 2);
                $taxLines['SGST'] = ($taxLines['SGST'] ?? 0) + ($pumpRateTax / 2);
            } else {
                $taxLines[$g] = ($taxLines[$g] ?? 0) + $pumpRateTax;
            }
        }

        $computedTaxLines = self::formatTaxLines($taxLines);
        $data['totals']   = [
            'sub_total'   => (float) $subTotalVal,
            'pump_rate'   => (float) $pumpRateUntaxed,
            'discount'    => 0,
            'tax_lines'   => $computedTaxLines,
            'shipping'    => 0,
            'adjustment'  => 0,
            'round_off'   => 0,
            'grand_total' => (float) ($grandTotalVal + $pumpRateTotal),
        ];

        $salesExec       = $salesOrder->salesExecutive;
        $salesPersonName = self::formatSalesExecutiveName($salesExec);

        $data['meta'] = [
            'currency_code'          => 'INR',
            'currency_symbol'        => '₹',
            'notes'                  => $salesOrder->terms_conditions ?? '',
            'terms_text'             => self::resolveTermsCondition($data['settings'], 'Sales Order', $salesOrder->plant_id, $salesOrder->terms_conditions ?? ''),
            'total_words'            => self::numberToWords($data['totals']['grand_total'], 'INR'),
            'po_number'              => $salesOrder->customerPO?->customer_po_reference ?: ($salesOrder->customerPO?->reference ?? ''),
            'project_name'           => $salesOrder->site?->name ?? '',
            'pump'                   => $pumpTypeLabel !== '-' ? $pumpTypeLabel : '',
            'sales_person'           => $salesPersonName,
            'sales_executive_name'    => $salesPersonName,
            'sales_executive_mobile'  => self::formatSalesExecutiveMobile($salesExec),
        ];

        return $data;
    }

    public static function fromDeliveryChallan($batch): array
    {
        $batch->loadMissing([
            'salesOrder', 'salesOrder.customer', 'salesOrder.site', 'salesOrder.plant', 'salesOrder.plant.entity',
            'salesOrder.plant.addresses', 'salesOrder.mixDesign', 'salesOrder.mixDesign.concrete_grade', 'salesOrder.mixDesign.unit',
            'dispatches', 'dispatches.truck', 'dispatches.driver', 'dispatches.transport', 'dispatches.loadTax', 'materials.product',
            'materials.uom', 'operator'
        ]);

        $data              = self::base();
        $data['settings']  = self::getCustomSettings($batch->salesOrder->plant_id, 'delivery_challans');
        $data['doc_title'] = $data['settings']['pdf']['labels']['invoice_title'] ?? 'DELIVERY CHALLAN';
        $data['doc_no']    = strtoupper('B' . ($batch->batch_no ?? $batch->id));
        $data['doc_date']  = optional($batch->load_time ?? $batch->created_at)->format('d/m/Y H:i');

        $dispatch              = $batch->dispatches->first();
        $data['delivery_date'] = $dispatch?->load_time ? Carbon::parse($dispatch->load_time)->format('d/m/Y H:i') : ($batch->load_time ? $batch->load_time->format('d/m/Y H:i') : 'N/A');
        $data['state']         = $batch->status_text ?? 'DISPATCHED';

        $data['company'] = self::formatCompany($batch->salesOrder->plant);
        $data['bill_to'] = self::formatPartner($batch->salesOrder->customer);
        $data['ship_to'] = self::formatShipTo($batch->salesOrder->site, $data['bill_to']);

        $settings           = CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');
        $printMode          = $settings['material_print_mode'] ?? 'run';
        $formattedMaterials = $batch->getFormattedMaterials($printMode);

        $groupedMaterials = $formattedMaterials->groupBy(fn ($mat) => $mat->material_name)->map(function ($group) {
            $first  = $group->first();
            $target = $group->sum('target_qty');
            $actual = $group->sum('actual_qty');
            return (object) [
                'material_name'      => $first->material_name,
                'uom_code'           => $first->uom_code,
                'hsn_code'           => '-',
                'target_qty'         => $target,
                'actual_qty'         => $actual,
                'deviation_quantity' => $group->sum('deviation_quantity'),
            ];
        });

        $itemsList = [];
        if ($dispatch) {
            $mixDesign           = $batch->salesOrder?->mixDesign;
            $printItemNameFormat = self::getPrintItemNameFormat($data['settings'], $batch->salesOrder?->plant_id);
            $mixDesignName       = self::resolvePrintedItemName($mixDesign, $printItemNameFormat);
            $qty                 = (float) ($dispatch->delivered_qty ?: $batch->batch_size);
            $rate                = (float) ($dispatch->load_rate ?? 0);
            $subTotal            = (float) ($dispatch->load_untax_amount ?? ($qty * $rate));
            $taxAmount           = (float) ($dispatch->load_tax_amount ?? 0);
            $totalAmount         = (float) ($dispatch->load_total_amount ?? ($subTotal + $taxAmount));
            $taxRate             = $dispatch->loadTax?->rate ?? 0;
            $taxName             = $dispatch->loadTax?->name ?? '-';

            $itemsList[] = [
                'no' => 1, 'name' => $mixDesignName, 'grade' => $mixDesignName, 'description' => '', 'hsn' => '38245010',
                'qty' => $qty, 'received_qty' => $qty, 'unit' => $dispatch->uom?->unit_code ?? 'CBM', 'unit_price' => $rate,
                'tax_name' => $taxName, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'total' => $totalAmount,
            ];
        }

        $sno = count($itemsList) + 1;
        foreach ($groupedMaterials->values() as $item) {
            $target       = (float) $item->target_qty;
            $actual       = (float) $item->actual_qty;
            $deviationVal = $actual - $target;
            $devPercent   = $target > 0 ? ($deviationVal / $target) * 100 : 0;
            $devSign      = $devPercent > 0 ? '+' : '';
            $desc         = sprintf("Target: %s %s | Actual: %s %s | Dev: %s%s%%", number_format($target, 2), $item->uom_code, number_format($actual, 2), $item->uom_code, $devSign, number_format($devPercent, 2));

            $itemsList[] = [
                'no' => $sno++, 'name' => $item->material_name, 'description' => $desc, 'hsn' => $item->hsn_code,
                'qty' => $actual ?: $target, 'received_qty' => $actual, 'unit' => $item->uom_code, 'unit_price' => 0.00,
                'tax_name' => '-', 'tax_rate' => 0.00, 'tax_amount' => 0.00, 'total' => 0.00,
            ];
        }
        $data['items'] = $itemsList;

        $taxLines = [];
        if ($dispatch && $dispatch->load_tax_amount > 0) {
            $taxLines[] = ['name' => $dispatch->loadTax?->name ?? 'GST', 'rate' => $dispatch->loadTax?->rate ?? 18, 'amount' => (float) $dispatch->load_tax_amount];
        }

        $data['totals'] = [
            'sub_total'   => (float) ($dispatch?->load_untax_amount ?? 0),
            'discount'    => (float) ($dispatch?->discount_amount ?? 0),
            'tax_lines'   => $taxLines,
            'shipping'    => (float) ($dispatch?->transport_expenses ?? 0),
            'adjustment'  => (float) ($dispatch?->adjustment_amount ?? 0),
            'round_off'   => (float) ($dispatch?->round_off ?? 0),
            'grand_total' => (float) ($dispatch?->load_total_amount ?? 0),
        ];

        $emptyWeight    = (float) ($dispatch?->empty_weight_truck ?? 0);
        $loadedWeight   = (float) ($dispatch?->loaded_weight_truck ?? 0);
        $netWeight      = (float) ($dispatch?->net_weight ?? ($loadedWeight - $emptyWeight));
        $emptyWeightStr = number_format($emptyWeight, 0) . ' kg';
        $loadedWeightStr = number_format($loadedWeight, 0) . ' kg';
        $netWeightStr   = number_format($netWeight, 0) . ' kg';

        $driverName  = self::formatCarrierDriverDetails(null, null, $dispatch?->driver);
        $weightNotes = "VEHICLE WEIGHT DETAILS:\n"
            . "Truck No: " . ($dispatch?->truck?->registration ?? '-') . "\n"
            . "Driver: " . ($driverName !== '-' ? $driverName : '-') . "\n"
            . "Empty Weight: " . $emptyWeightStr . " (" . ($dispatch?->empty_time ? Carbon::parse($dispatch->empty_time)->format('d-m-Y H:i') : '-') . ")\n"
            . "Loaded Weight: " . $loadedWeightStr . " (" . ($dispatch?->load_time ? Carbon::parse($dispatch->load_time)->format('d-m-Y H:i') : '-') . ")\n"
            . "Net Weight: " . $netWeightStr . "\n\n"
            . "Batch size: " . number_format((float) $batch->batch_size, 2) . " m³\n"
            . "Concrete Grade: " . self::resolveMixDesignName($batch->salesOrder?->mixDesign);

        $data['meta'] = [
            'currency_code'   => 'INR',
            'currency_symbol' => '₹',
            'notes'           => $weightNotes,
            'terms_text'      => self::resolveTermsCondition($data['settings'], 'Delivery Challan', $batch->salesOrder->plant_id, $batch->salesOrder?->terms_conditions ?? "1. Goods received in good condition.\n2. Any variation in quantity to be reported immediately."),
            'total_words'     => '',
            'po_number'       => $batch->salesOrder?->order_no ?? '-',
            'project_name'    => 'Concrete Grade: ' . self::resolveMixDesignName($batch->salesOrder?->mixDesign),
        ];
        $data['batch'] = $batch;

        return $data;
    }
}
