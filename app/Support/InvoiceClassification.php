<?php

namespace App\Support;

final class InvoiceClassification
{
    /** Historical values remain readable while all new writes use Invoice / Bill. */
    public static function aliases(string $type): array
    {
        return match (strtolower($type)) {
            'invoice', 'sales' => ['Invoice', 'INVOICE', 'invoice', 'sales', 'Sales', 'SALES'],
            'bill', 'purchase' => ['Bill', 'BILL', 'bill', 'purchase', 'Purchase', 'PURCHASE'],
            default => [$type],
        };
    }

    public static function normalizeInvoiceType(?string $type): ?string
    {
        return match (strtolower(trim((string) $type))) {
            'invoice', 'sales' => 'Invoice',
            'bill', 'purchase' => 'Bill',
            default => $type,
        };
    }
}
