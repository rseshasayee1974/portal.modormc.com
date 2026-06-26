<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\TaxLineDTO;
use App\DTO\AdjustmentLineDTO;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Contract that Invoice, Bill, Expense, Payment, Receipt must all implement.
 * AccountingPostingService only depends on this interface — not on concrete models.
 */
interface Postable
{
    public function getDocumentId(): int;
    public function getDocumentType(): string;        // 'invoice'|'bill'|'expense'|'payment'|'receipt'
    public function getVoucherNumber(): string;
    public function getVoucherDate(): Carbon;
    public function getPlantId(): int;               // NEVER falls back to session
    public function getEntityId(): int;
    public function getPartnerId(): ?int;
    public function getPartnerLedgerId(): ?int;
    public function getPartnerName(): string;
    public function getBaseAccountId(): ?int;
    public function getContraAccountId(): ?int;       // bank/cash ledger for payment/receipt

    // All amounts as INTEGER CENTS to avoid float accumulation errors
    public function getSubtotalCents(): int;
    public function getTaxTotalCents(): int;
    public function getTotalAmountCents(): int;

    public function getTaxLines(): Collection;        // Collection<TaxLineDTO>
    public function getAdjustmentLines(): Collection; // Collection<AdjustmentLineDTO>
}
