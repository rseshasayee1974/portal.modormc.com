<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Immutable value object representing a single adjustment line.
 * amountCents: positive = charge, negative = discount
 */
readonly class AdjustmentLineDTO
{
    public function __construct(
        public int    $amountCents,
        public ?int   $accountId,
        public string $label,
    ) {}
}
