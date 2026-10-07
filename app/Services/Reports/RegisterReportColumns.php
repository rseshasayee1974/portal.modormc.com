<?php

namespace App\Services\Reports;

class RegisterReportColumns
{
    public static function value(array $row, string $key): mixed
    {
        // Tax keys contain decimal points, so they are not dot-notation paths.
        return str_starts_with($key, 'taxes.')
            ? ($row['taxes'][substr($key, 6)] ?? 0)
            : ($row[$key] ?? '');
    }

    /** Excel keeps only the primary document number supplied by the register format. */
    public static function forExcel(array $columns): array
    {
        $numberKey = in_array('invoice_no', array_column($columns, 'key'), true) ? 'invoice_no' : 'bill_no';
        return array_values(array_filter($columns, fn ($column) =>
            !in_array($column['key'], ['invoice_no', 'bill_no', 'po_number'], true) || $column['key'] === $numberKey
        ));
    }

    /** One column contract for the screen, Excel and PDF. */
    public static function for(string $type, string $view, array $taxColumns): array
    {
        if ($type === 'sales_register' && $view === 'detail') {
            return self::detailedSales($taxColumns);
        }
        $sales = $type === 'sales_register';
        $columns = [];
        $add = function ($key, $label, $format = 'text', $total = null) use (&$columns) {
            $columns[] = compact('key', 'label', 'format', 'total');
        };
        $add($sales ? 'invoice_date' : 'bill_date', 'Date', 'date');
        $add($sales ? 'customer_name' : 'supplier_name', $sales ? 'Customer' : 'Supplier');
        $add('gst_number', 'GSTIN');
        $add('payment_mode', 'Type');
        $add($sales ? 'invoice_no' : 'bill_no', $sales ? 'Invoice No' : 'Bill No');
        $add($sales ? 'bill_no' : 'po_number', $sales ? 'Bill No' : 'PO No');
        if ($view === 'detail') {
            $add('document_type', 'Type');
            $add('product_name', 'Product');
            $add('hsn_code', 'HSN/SAC');
            $add('qty', 'Quantity', 'number', 'qty');
            $add('unit', 'Unit');
            $add($sales ? 'rate' : 'purchase_rate', 'Rate', 'number');
        }
        $add('discount', 'Discount', 'number', 'discount');
        $add('taxable_amount', 'Taxable Amount', 'number', 'taxable');
        if ($sales) {
            $add('net_amount', 'Net Amount', 'number', 'grand_total');
        }
        foreach ($taxColumns as $column) {
            $add('taxes.'.$column['key'], $column['label'], 'number', 'taxes.'.$column['key']);
        }
        $add('tax_amount', 'Total Tax', 'number', 'gst');
        $add('roundoff', 'Round Off', 'number', 'roundoff');
        if (!$sales) {
            $add('net_amount', 'Net Amount', 'number', 'grand_total');
        }
        if ($view === 'detail') {
            if ($sales) {
                $add('irn', 'IRN');
                $add('ack_date', 'ACK Date');
                $add('cancel_at', 'Cancelled At');
            }
            $add('created_by', 'Created By');
        }
        return $columns;
    }

    private static function detailedSales(array $taxColumns): array
    {
        $columns = [];
        $add = function ($key, $label, $format = 'text', $total = null) use (&$columns) {
            $columns[] = compact('key', 'label', 'format', 'total');
        };
        $add('invoice_date', 'Date', 'date');
        $add('customer_name', 'Party');
        $add('gst_number', 'GSTIN');
        $add('payment_mode', 'Type');
        $add('invoice_no', 'Invoice No');
        $add('bill_no', 'Bill No');
        $add('product_name', 'Product');
        $add('hsn_code', 'HSN/SAC');
        $add('qty', 'Quantity', 'number', 'qty');
        $add('rate', 'Product Rate', 'number');
        $add('unit', 'Unit');
        $add('discount', 'Discount', 'number', 'discount');
        $add('tax_name', 'Tax Name');
        $add('taxable_amount', 'Sales GST (Taxable)', 'number', 'taxable');
        $add('net_amount', 'Net Amount', 'number', 'grand_total');
        foreach ($taxColumns as $column) {
            $add('taxes.'.$column['key'], $column['label'], 'number', 'taxes.'.$column['key']);
        }
        $add('tcs', 'TCS', 'number', 'tcs');
        $add('truck', 'Truck');
        $add('tax_amount', 'Total Tax', 'number', 'gst');
        $add('roundoff', 'Round Off', 'number', 'roundoff');
        $add('unloading', 'Unloading Point');
        $add('irn', 'IRN');
        $add('einvoice_status', 'E-Invoice Status');
        $add('ack_date', 'ACK Date');
        $add('cancel_at', 'Cancel At');
        $add('created_by', 'Created By');
        return $columns;
    }

    /** Arrange standard sales Excel by party, document, delivery, then tax amounts. */
    public static function standardSalesExcel(array $columns): array
    {
        $existingKeys = array_column($columns, 'key');
        
        $addressExtras = [
            ['key' => 'address_1', 'label' => 'Address_1', 'format' => 'text', 'total' => null],
            ['key' => 'address_2', 'label' => 'Address_2', 'format' => 'text', 'total' => null],
            ['key' => 'city', 'label' => 'City', 'format' => 'text', 'total' => null],
            ['key' => 'zipcode', 'label' => 'Zipcode', 'format' => 'text', 'total' => null],
            ['key' => 'shipping_address_1', 'label' => 'Shipping Address_1', 'format' => 'text', 'total' => null],
            ['key' => 'shipping_address_2', 'label' => 'Shipping Address_2', 'format' => 'text', 'total' => null],
            ['key' => 'shipping_zipcode', 'label' => 'Shipping Zipcode', 'format' => 'text', 'total' => null],
            ['key' => 'truck', 'label' => 'Truck', 'format' => 'text', 'total' => null],
        ];

        $addressDetails = [];
        foreach ($addressExtras as $extra) {
            if (!in_array($extra['key'], $existingKeys, true)) {
                $addressDetails[] = $extra;
            }
        }

        $result = [];
        foreach ($columns as $column) {
            if ($column['key'] === 'invoice_no') {
                if (!in_array('payment_mode', $existingKeys, true)) {
                    $result[] = ['key' => 'payment_mode', 'label' => 'Type', 'format' => 'text', 'total' => null];
                }
            }
            if ($column['key'] === 'taxable_amount') {
                if (!in_array('discount', $existingKeys, true)) {
                    $result[] = ['key' => 'discount', 'label' => 'Discount', 'format' => 'number', 'total' => 'discount'];
                }
            }
            if ($column['key'] === 'net_amount') {
                if (!in_array('roundoff', $existingKeys, true)) {
                    $result[] = ['key' => 'roundoff', 'label' => 'Round Off', 'format' => 'number', 'total' => 'roundoff'];
                }
            }

            $result[] = $column;
            
            if ($column['key'] === 'customer_name') {
                array_push($result, ...$addressDetails);
            }
        }
        $byKey = array_column(self::forExcel($result), null, 'key');
        foreach (array_keys($byKey) as $key) {
            if (in_array($key, ['tcs', 'party_type', 'description'], true) || str_starts_with($key, 'taxes.TCS_')) unset($byKey[$key]);
        }
        foreach (['customer_name' => 'Customer', 'rate' => 'Rate', 'taxable_amount' => 'Taxable Amount', 'net_amount' => 'Net Amount'] as $key => $label) {
            if (isset($byKey[$key])) $byKey[$key]['label'] = $label;
        }

        $result = [];
        foreach ([
            'invoice_date', 'customer_name', 'address_1', 'address_2', 'city', 'zipcode',
            'shipping_address_1', 'shipping_address_2', 'shipping_zipcode', 'gst_number', 'payment_mode', 'invoice_no',
            'product_name', 'truck', 'hsn_code', 'qty', 'unit', 'rate',
            'discount', 'tax_name', 'taxable_amount', 'net_amount',
        ] as $key) {
            if (!isset($byKey[$key])) continue;
            $result[] = $byKey[$key];
            unset($byKey[$key]);
        }
        $ending = [];
        foreach (['tax_amount', 'roundoff', 'unloading', 'irn', 'einvoice_status', 'ack_date', 'cancel_at', 'created_by'] as $key) {
            if (!isset($byKey[$key])) continue;
            $ending[] = $byKey[$key];
            unset($byKey[$key]);
        }
        // The remaining rate columns keep their paired GST order, including additional recorded rates.
        return array_merge($result, array_values($byKey), $ending);
    }
}
