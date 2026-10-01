<?php

namespace App\Services\Reports;

use App\Models\Patron;
use App\Models\Plant;
use Illuminate\Support\Facades\DB;

class RegisterAddressGstFormat
{
    public const KEY = 'address_gst';

    public function prepare(array $report, string $type, int $plantId): array
    {
        $report['data'] = $this->rowsWithDetails($report['data'], $type, $plantId);
        $report['columns'] = $this->columns();
        $report['totals']['roundoff'] = round(array_sum(array_column($report['data'], 'roundoff')), 2);
        $report['totals']['discount'] = round(array_sum(array_column($report['data'], 'discount')), 2);
        $report['excel_format'] = self::KEY;
        $report['note'] = 'GROSS is the stored item total including tax; SALES GST is the taxable item value. ROUNDOFF appears once per invoice/bill on its first matching item. Amounts are in INR.';
        return $report;
    }

    public function prepareStandardSales(array $report, int $plantId): array
    {
        $report['data'] = $this->rowsWithDetails($report['data'], 'sales_register', $plantId);
        $report['columns'] = RegisterReportColumns::standardSalesExcel($report['columns']);
        $report['totals']['roundoff'] = round(array_sum(array_column($report['data'], 'roundoff')), 2);
        $report['totals']['discount'] = round(array_sum(array_column($report['data'], 'discount')), 2);
        return $report;
    }

    private function rowsWithDetails(array $sourceRows, string $type, int $plantId): array
    {
        $sales = $type === 'sales_register';
        $rows = [];
        $seenDocuments = [];
        $plant = $sales ? null : Plant::where('id', $plantId)->with(['addresses.state', 'addresses.addressType'])->first();
        $plantAddress = $this->preferredAddress($plant?->addresses ?? collect(), $plantId);

        // Batch lookups so addresses and delivery details do not add a query per item.
        foreach (array_chunk($sourceRows, 500) as $chunk) {
            $ids = array_values(array_unique(array_column($chunk, 'document_id')));
            $documents = $sales ? $this->salesDocuments($ids, $plantId) : $this->purchaseDocuments($ids, $plantId);
            $parties = Patron::withoutGlobalScope('active_operational_status')->where('plant_id', $plantId)->whereIn('id', $documents->pluck('party_id')->filter())
                ->with(['addresses.state', 'addresses.addressType', 'contacts.addresses.state', 'contacts.addresses.addressType'])
                ->get()->keyBy('id');
            $trucks = $sales ? collect() : DB::table('mm_purchase_order_history as history')
                ->join('mm_machines as truck', function ($join) use ($plantId) {
                    $join->on('truck.id', '=', 'history.truck_id')->where('truck.plant_id', $plantId)->whereNull('truck.deleted_at');
                })->where('history.plant_id', $plantId)->whereIn('history.order_id', $ids)
                ->whereNull('history.deleted_at')->orderBy('history.id')
                ->get(['history.order_id', 'history.order_item_id', 'truck.registration'])
                ->groupBy(fn ($row) => $row->order_id.':'.$row->order_item_id);

            foreach ($chunk as $row) {
                $document = $documents->get($row['document_id']);
                $party = $parties->get($document?->party_id);
                $contact = $party?->contacts->filter(fn ($contact) => !$contact->plant_id || (int) $contact->plant_id === $plantId)
                    ->sortBy([['is_primary', 'desc'], ['id', 'asc']])->first();
                $address = $this->preferredAddress($contact?->addresses ?? collect(), $plantId)
                    ?? $this->preferredAddress($party?->addresses ?? collect(), $plantId);
                $roundoff = isset($seenDocuments[$row['document_id']]) ? 0.0 : (float) ($document?->roundoff ?? 0);
                $discount = isset($seenDocuments[$row['document_id']]) ? 0.0 : (float) ($document?->discount ?? 0);
                $seenDocuments[$row['document_id']] = true;
                $shippingParts = $sales
                    ? [$document?->shipping_address_1, $document?->shipping_address_2, $document?->shipping_city]
                    : [$plantAddress?->line_1, $plantAddress?->line_2, $plantAddress?->city];
                $shipping = implode(', ', array_filter($shippingParts, fn ($value) => trim((string) $value) !== ''));

                $rows[] = array_merge($row, [
                    'date' => $row[$sales ? 'invoice_date' : 'bill_date'],
                    'party' => $row[$sales ? 'customer_name' : 'supplier_name'],
                    'address_1' => $address?->line_1 ?? '', 'address_2' => $address?->line_2 ?? '',
                    'city' => $address?->city ?? '', 'state' => $address?->state?->state_name ?? $address?->state_code ?? '',
                    'zipcode' => $address?->zipcode ?? '',
                    'shipping_address' => $shipping ?: ($sales ? ($document?->shipping_name ?? '') : ''),
                    'shipping_address_1' => $sales ? ($document?->shipping_address_1 ?? '') : ($plantAddress?->line_1 ?? ''),
                    'shipping_address_2' => $sales ? ($document?->shipping_address_2 ?? '') : ($plantAddress?->line_2 ?? ''),
                    'shipping_zipcode' => $sales ? ($document?->shipping_zipcode ?? '') : ($plantAddress?->zipcode ?? ''),
                    'payment_mode' => $sales ? ($row['payment_mode'] ?? '') : '',
                    'invoice_no' => $row[$sales ? 'invoice_no' : 'bill_no'],
                    'truck' => $sales ? ($row['truck'] ?? '') : $trucks->get($row['document_id'].':'.$row['id'], collect())->pluck('registration')->unique()->implode(', '),
                    'rate' => $row[$sales ? 'rate' : 'purchase_rate'],
                    'gross' => $row['net_amount'],
                    'tax_name' => $row['tax_name'] ?? $this->taxName($row['taxes'] ?? []),
                    'discount' => round($discount, 2),
                    'roundoff' => round($roundoff, 2),
                ]);
            }
        }

        return $rows;
    }

    private function salesDocuments(array $ids, int $plantId)
    {
        return DB::table('mm_invoices as invoice')
            ->leftJoin('mm_dispatches as dispatch', function ($join) use ($plantId) {
                $join->on('dispatch.id', '=', 'invoice.ref_id')->where('invoice.invoice_label', 'Dispatch')
                    ->where('dispatch.plant_id', $plantId)->whereNull('dispatch.deleted_at');
            })->leftJoin('mm_sales_orders as sales_order', function ($join) use ($plantId) {
                $join->on('sales_order.id', '=', 'dispatch.sales_order_id')->where('sales_order.plant_id', $plantId)->whereNull('sales_order.deleted_at');
            })->leftJoin('mm_sites as site', function ($join) use ($plantId) {
                $join->on('site.id', '=', 'dispatch.unload_site_id')->where('site.plant_id', $plantId)->whereNull('site.deleted_at');
            })->where('invoice.plant_id', $plantId)->where('invoice.invoice_type', 'sales')->whereIn('invoice.id', $ids)->whereNull('invoice.deleted_at')
            ->get(['invoice.id', DB::raw('COALESCE(NULLIF(invoice.partner_id, 0), NULLIF(dispatch.customer_id, 0), sales_order.customer_id) as party_id'),
                'invoice.round_off as roundoff', 'invoice.discount_total as discount', 'site.name as shipping_name', 'site.site_address_1 as shipping_address_1',
                'site.site_address_2 as shipping_address_2', 'site.city as shipping_city', 'site.zipcode as shipping_zipcode'])->keyBy('id');
    }

    private function purchaseDocuments(array $ids, int $plantId)
    {
        return DB::table('mm_purchase_orders')->where('plant_id', $plantId)->whereIn('id', $ids)->whereNull('deleted_at')
            ->get(['id', 'vendor_id as party_id', 'rounding_value as roundoff', 'discount_amount as discount'])->keyBy('id');
    }

    private function preferredAddress($addresses, int $plantId)
    {
        return $addresses->filter(fn ($address) => !$address->plant_id || (int) $address->plant_id === $plantId)
            ->sortBy(fn ($address) => [str_contains(strtolower($address->addressType?->type ?? ''), 'billing') ? 0 : 1,
                $address->is_primary ? 0 : 1, $address->id])->first();
    }

    private function taxName(array $taxes): string
    {
        $rates = [];
        foreach (array_keys($taxes) as $key) {
            [$type, $rate] = explode('_', $key, 2);
            $rates[$type] = ($rates[$type] ?? 0) + (float) $rate;
        }
        if (isset($rates['CGST']) && (isset($rates['SGST']) || isset($rates['UTGST']))) {
            $rates = ['GST' => $rates['CGST'] + ($rates['SGST'] ?? 0) + ($rates['UTGST'] ?? 0)]
                + array_diff_key($rates, array_flip(['CGST', 'SGST', 'UTGST']));
        }
        return implode(' + ', array_map(fn ($type, $rate) => $type.' '.$rate.'%', array_keys($rates), $rates));
    }

    public function columns(): array
    {
        $columns = [];
        foreach ([
            ['date', 'DATE', 'date'], ['party', 'PARTY'], ['address_1', 'ADDRESS_1'], ['address_2', 'ADDRESS_2'],
            ['city', 'CITY'], ['state', 'STATE'], ['zipcode', 'ZIPCODE'], ['shipping_address', 'SHIPPING ADDRESS'],
            ['shipping_zipcode', 'SHIPPING ZIPCODE'], ['payment_mode', 'TYPE (CASH OR CREDIT)'], ['invoice_no', 'INVOICE NO'],
            ['truck', 'TRUCK'], ['gst_number', 'GSTIN'], ['product_name', 'PRODUCT'], ['hsn_code', 'HSN/SAC'],
            ['qty', 'QUANTITY', 'number', 'qty'], ['unit', 'UNIT'], ['rate', 'RATE', 'number'],
            ['gross', 'GROSS', 'number', 'grand_total'], ['discount', 'DISCOUNT', 'number', 'discount'], ['taxable_amount', 'SALES GST', 'number', 'taxable'],
            ['tax_name', 'TAX NAME'], ['tax_amount', 'TAX AMOUNT', 'number', 'gst'],
            ['cgst', 'CGST', 'number', 'cgst'], ['sgst', 'SGST', 'number', 'sgst'], ['igst', 'IGST', 'number', 'igst'],
            ['roundoff', 'ROUND OFF', 'number', 'roundoff'],
        ] as $column) {
            $columns[] = ['key' => $column[0], 'label' => $column[1], 'format' => $column[2] ?? 'text', 'total' => $column[3] ?? null];
        }
        return $columns;
    }
}
