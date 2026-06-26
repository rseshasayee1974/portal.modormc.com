<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Immutable value object representing a single tax line.
 * amountCents: integer cents (avoids float drift in accumulation)
 */
readonly class TaxLineDTO
{
    public function __construct(
        public int    $amountCents,
        public ?int   $accountId,
        public string $taxName,
        public ?int   $taxId,
    ) {}
}
