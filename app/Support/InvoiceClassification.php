<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class InvoiceClassification
{
    public const TYPES = ['INVOICE', 'BILL'];
    public const SOURCES = ['DISPATCH', 'PURCHASE_STOCKIN', 'MANUAL'];

    public static function normalizeType(mixed $value): mixed
    {
        return is_string($value) ? strtoupper(trim($value)) : $value;
    }

    public static function normalizeSource(mixed $value): mixed
    {
        return is_string($value) ? strtoupper(trim($value)) : $value;
    }

    /** Historical values remain readable while all new writes use Invoice / Bill. */
    public static function aliases(string $type): array
    {
        return match (strtolower($type)) {
            'invoice', 'sales' => ['Invoice', 'INVOICE', 'invoice', 'sales', 'Sales', 'SALES'],
            'bill', 'purchase' => ['Bill', 'BILL', 'bill', 'purchase', 'Purchase', 'PURCHASE'],
            default => [$type],
        };
    }

    public static function fromLegacy(?string $type, ?string $label): array
    {
        $type = match (strtolower(trim((string) $type))) {
            'sales', 'invoice' => 'INVOICE',
            'purchase', 'bill' => 'BILL',
            default => null,
        };
        // Credit/debit notes keep their existing semantics and have no commercial classification.
        $source = $type === null ? null : match (strtolower(trim((string) $label))) {
            'dispatch', 'batching' => 'DISPATCH',
            'purchase', 'stockin', 'stock-in', 'stock_in', 'purchase/stockin', 'purchase_stockin' => 'PURCHASE_STOCKIN',
            default => 'MANUAL',
        };

        return ['document_type' => $type, 'document_source' => $source];
    }

    public static function validate(?string $type, ?string $source): void
    {
        $errors = [];
        if ($type !== null && !in_array($type, self::TYPES, true)) {
            $errors['document_type'] = 'Document type must be INVOICE or BILL.';
        }
        if ($source !== null && !in_array($source, self::SOURCES, true)) {
            $errors['document_source'] = 'Document source must be DISPATCH, PURCHASE_STOCKIN or MANUAL.';
        }
        if (($source === 'DISPATCH' && $type !== 'INVOICE') || ($source === 'PURCHASE_STOCKIN' && $type !== 'BILL')) {
            $errors['document_source'] = 'Dispatch requires INVOICE; Purchase/Stock-In requires BILL.';
        }
        if ($type === null && $source !== null) {
            $errors['document_type'] = 'A document source requires a document type.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
