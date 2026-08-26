<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Contracts\Postable;
use App\DTO\TaxLineDTO;
use App\DTO\AdjustmentLineDTO;
use App\Exceptions\AccountingException;
use Illuminate\Support\Facades\Log;

/**
 * Assembles a balanced set of journal entry lines for any Postable document.
 *
 * All amounts are tracked as integer cents internally.
 * Converted to decimal only at persistence time.
 *
 * Rules:
 *  - partner_side  from DocumentTypeConfig determines debit/credit for partner line
 *  - base_side     determines debit/credit for revenue/expense/bank line
 *  - tax_side      determines debit/credit for each tax split line
 *  - Any imbalance throws — no tolerance accepted
 */
class JournalEntryBuilder
{
    private array $lines = [];
    private int $totalDebitCents  = 0;
    private int $totalCreditCents = 0;

    public function __construct(
        private readonly Postable       $document,
        private readonly array          $config,   // from DocumentTypeConfig::get()
        private readonly LedgerResolver $ledger,
    ) {}

    /**
     * Build and return all lines. Throws AccountingException on any failure.
     *
     * @return array[]
     * @throws AccountingException
     */
    public function build(): array
    {
        $this->addPartnerLine();
        $this->addBaseLine();
        $this->addTaxLines();
        $this->addAdjustmentLines();
        $this->assertBalanced();

        return $this->lines;
    }

    // -------------------------------------------------------------------------

    private function addPartnerLine(): void
    {
        $totalCents = $this->document->getTotalAmountCents();
        $side       = $this->config['partner_side'];

        $ledgerId = $this->document->getPartnerLedgerId()
            ?? $this->ledger->resolve(
                $this->document->getPlantId(),
                $this->config['module'],
                $this->config['partner_setting'],
                $side === 'debit' ? 'Sundry Debtor' : 'Sundry Creditor'
            );

        if (!$ledgerId) {
            throw new AccountingException(
                "Missing partner ledger for '{$this->document->getPartnerName()}'. "
                . "Configure '{$this->config['partner_setting']}' in Account Default Settings."
            );
        }

        $narration = $side === 'debit' ? 'Receivable' : 'Payable';

        $this->pushLine($side, $totalCents, [
            'account_id'     => $ledgerId,
            'partner_id'     => $this->document->getPartnerId(),
            'narration_name' => $narration,
            'line_narration' => "Invoice #{$this->document->getVoucherNumber()}",
        ]);
    }

    private function addBaseLine(): void
    {
        $subtotal = $this->document->getSubtotalCents();
        if ($subtotal === 0) return;

        $side = $this->config['base_side'];

        $ledgerId = $this->document->getBaseAccountId()
            ?? $this->ledger->resolve(
                $this->document->getPlantId(),
                $this->config['module'],
                $this->config['base_setting'],
                $side === 'credit' ? 'Sales' : 'Purchase'
            );

        if (!$ledgerId) {
            throw new AccountingException(
                "Missing base ledger for doc type '{$this->document->getDocumentType()}'. "
                . "Configure '{$this->config['base_setting']}' in Account Default Settings."
            );
        }

        $this->pushLine($side, $subtotal, [
            'account_id'     => $ledgerId,
            'narration_name' => ucfirst($this->config['base_side'] === 'credit' ? 'Revenue' : 'Expense'),
            'line_narration' => "Base amount for #{$this->document->getVoucherNumber()}",
        ]);
    }

    private function addTaxLines(): void
    {
        $taxSide     = $this->config['tax_side'];
        if ($taxSide === null) return; // payment/receipt has no tax

        $expectedCents = $this->document->getTaxTotalCents();
        $sumTaxCents   = 0;
        $voucherNo     = $this->document->getVoucherNumber();

        foreach ($this->document->getTaxLines() as $tax) {
            /** @var TaxLineDTO $tax */
            if ($tax->amountCents === 0) continue;

            $accountId = $tax->accountId
                ?? $this->resolveTaxAccountByName($tax->taxName);

            if (!$accountId) {
                // Log and skip — tax will be caught by reconciliation below
                Log::warning("Accounting: No account mapped for tax [{$tax->taxName}]. "
                    . "Will use fallback tax_account if available.", [
                    'document_id'   => $this->document->getDocumentId(),
                    'document_type' => $this->document->getDocumentType(),
                ]);
                continue;
            }

            $this->pushLine($taxSide, $tax->amountCents, [
                'account_id'     => $accountId,
                'tax_id'         => $tax->taxId,
                'narration_name' => $tax->taxName,
                'line_narration' => "{$tax->taxName} on #{$voucherNo}",
            ]);

            $sumTaxCents += $tax->amountCents;
        }

        // Exact reconciliation — no tolerance
        $diff = $expectedCents - $sumTaxCents;
        if ($diff !== 0) {
            $fallbackId = $this->ledger->resolve(
                $this->document->getPlantId(),
                $this->config['module'],
                'tax_account',
                'Tax'
            );

            if (!$fallbackId) {
                // If no explicit fallback 'tax_account' setting exists, fall back to the first mapped tax line account
                foreach ($this->document->getTaxLines() as $tax) {
                    $accId = $tax->accountId ?? $this->resolveTaxAccountByName($tax->taxName);
                    if ($accId) {
                        $fallbackId = $accId;
                        break;
                    }
                }
            }

            if (!$fallbackId) {
                throw new AccountingException(
                    "Tax mismatch of {$this->centsDisplay($diff)} and no fallback 'tax_account' configured. "
                    . "Fix tax splits or map 'tax_account' in Account Default Settings."
                );
            }

            $this->pushLine($taxSide, $diff, [
                'account_id'     => $fallbackId,
                'narration_name' => 'Tax Adjustment',
                'line_narration' => "Tax rounding adjustment for #{$voucherNo}",
            ]);
        }
    }

    private function addAdjustmentLines(): void
    {
        $voucherNo = $this->document->getVoucherNumber();

        foreach ($this->document->getAdjustmentLines() as $adj) {
            /** @var AdjustmentLineDTO $adj */
            if ($adj->amountCents === 0) continue;

            if (!$adj->accountId) {
                throw new AccountingException(
                    "Missing account for adjustment '{$adj->label}'. "
                    . "Configure the corresponding key in Account Default Settings."
                );
            }

            // Negative amount = discount.
            // Discount inverts the normal base_side (reduces the net amount).
            $isNegative = $adj->amountCents < 0;
            $baseSide   = $this->config['base_side'];

            // Normal charge: same side as base
            // Discount: opposite side to base
            $side = $isNegative
                ? ($baseSide === 'credit' ? 'debit' : 'credit')
                : $baseSide;

            $this->pushLine($side, abs($adj->amountCents), [
                'account_id'     => $adj->accountId,
                'narration_name' => $adj->label,
                'line_narration' => "{$adj->label} for #{$voucherNo}",
            ]);
        }
    }

    // -------------------------------------------------------------------------

    private function pushLine(string $side, int $amountCents, array $extra): void
    {
        $line = array_merge([
            'debit_cents'  => $side === 'debit'  ? $amountCents : 0,
            'credit_cents' => $side === 'credit' ? $amountCents : 0,
        ], $extra);

        $this->lines[]         = $line;
        $this->totalDebitCents  += $line['debit_cents'];
        $this->totalCreditCents += $line['credit_cents'];
    }

    private function assertBalanced(): void
    {
        if ($this->totalDebitCents !== $this->totalCreditCents) {
            throw new AccountingException(sprintf(
                "Unbalanced journal entry: Debit %s ≠ Credit %s (diff: %s). Posting aborted.",
                $this->centsDisplay($this->totalDebitCents),
                $this->centsDisplay($this->totalCreditCents),
                $this->centsDisplay(abs($this->totalDebitCents - $this->totalCreditCents))
            ));
        }
    }

    private function resolveTaxAccountByName(string $taxName): ?int
    {
        $lower = strtolower($taxName);
        $side  = $this->config['tax_side'];  // 'credit' for sales, 'debit' for purchase

        $key = match(true) {
            str_contains($lower, 'cgst') => $side === 'credit' ? 'cgst_output' : 'cgst_input',
            str_contains($lower, 'sgst') => $side === 'credit' ? 'sgst_output' : 'sgst_input',
            str_contains($lower, 'igst') => $side === 'credit' ? 'igst_output' : 'igst_input',
            default                      => null,
        };

        return $key
            ? $this->ledger->resolve($this->document->getPlantId(), $this->config['module'], $key, 'GST')
            : null;
    }

    private function centsDisplay(int $cents): string
    {
        return '₹' . number_format($cents / 100, 2);
    }
}
