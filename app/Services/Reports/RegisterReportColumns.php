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
        $add('taxable_amount', 'Taxable Amount', 'number', 'taxable');
        foreach ($taxColumns as $column) {
            $add('taxes.'.$column['key'], $column['label'], 'number', 'taxes.'.$column['key']);
        }
        $add('tax_amount', 'Total Tax', 'number', 'gst');
        $add('net_amount', 'Net Amount', 'number', 'grand_total');
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
        $add('invoice_no', 'Invoice No');
        $add('bill_no', 'Bill No');
        $add('product_name', 'Product');
        $add('hsn_code', 'HSN/SAC');
        $add('qty', 'Quantity', 'number', 'qty');
        $add('rate', 'Product Rate', 'number');
        $add('unit', 'Unit');
        $add('net_amount', 'Gross', 'number', 'grand_total');
        $add('tax_name', 'Tax Name');
        $add('taxable_amount', 'Sales GST (Taxable)', 'number', 'taxable');
        foreach ($taxColumns as $column) {
            $add('taxes.'.$column['key'], $column['label'], 'number', 'taxes.'.$column['key']);
        }
        $add('tcs', 'TCS', 'number', 'tcs');
        $add('unloading', 'Unloading');
        $add('truck', 'Truck');
        $add('description', 'Description');
        $add('irn', 'IRN');
        $add('einvoice_status', 'E-Invoice Status');
        $add('cancel_at', 'Cancel At');
        $add('ack_date', 'ACK Date');
        $add('party_type', 'Party Type');
        $add('created_by', 'Created By');
        $add('tax_amount', 'Total Tax', 'number', 'gst');
        return $columns;
    }
}
