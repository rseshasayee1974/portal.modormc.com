<?php

namespace App\Services\Finance;

use App\Models\BankStatementLine;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BankReconciliationService
{
    /**
     * Parse an uploaded bank statement CSV file and import its rows.
     *
     * @param string $filePath Absolute path to the uploaded CSV file
     * @param int $bankLedgerId Mapped cash/bank ledger ID
     * @param int $plantId Mapped plant ID
     * @return int Number of imported lines
     */
    public function importStatement(string $filePath, int $bankLedgerId, int $plantId): int
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("Statement file is missing or not readable.");
        }

        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file);

        if (!$headers) {
            fclose($file);
            throw new \Exception("CSV file is empty.");
        }

        // Auto-detect header column indices
        $indices = $this->detectHeaderIndices($headers);

        $importedCount = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($file)) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Extract values using indices
                $dateVal = $row[$indices['date']] ?? null;
                $descVal = $row[$indices['description']] ?? '';
                $refVal = $row[$indices['reference']] ?? null;
                $debitVal = $row[$indices['debit']] ?? '0';
                $creditVal = $row[$indices['credit']] ?? '0';
                $balanceVal = $row[$indices['balance']] ?? null;

                if (!$dateVal) {
                    continue; // Skip if date is missing
                }

                // Standardize date parsing
                $transactionDate = $this->parseDate($dateVal);
                if (!$transactionDate) {
                    continue; // Skip if date is invalid
                }

                // Standardize decimal values
                $debit = $this->parseAmount($debitVal);
                $credit = $this->parseAmount($creditVal);
                $balance = $balanceVal !== null ? $this->parseAmount($balanceVal) : null;

                BankStatementLine::create([
                    'plant_id' => $plantId,
                    'bank_ledger_id' => $bankLedgerId,
                    'transaction_date' => $transactionDate,
                    'value_date' => $transactionDate,
                    'description' => trim($descVal),
                    'reference_no' => $refVal ? trim($refVal) : null,
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    'balance' => $balance,
                ]);

                $importedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            Log::error("BRS Import Failure: " . $e->getMessage());
            throw $e;
        }

        fclose($file);
        return $importedCount;
    }

    /**
     * Get suggested system accounting ledger entry matches for a bank statement line.
     *
     * @param BankStatementLine $statementLine
     * @return \Illuminate\Support\Collection
     */
    public function getMatchingSuggestions(BankStatementLine $statementLine)
    {
        // Bank debit = withdrawal = Credit in our double-entry bank ledger
        // Bank credit = deposit = Debit in our double-entry bank ledger
        $isWithdrawal = $statementLine->debit_amount > 0;
        $amount = $isWithdrawal ? $statementLine->debit_amount : $statementLine->credit_amount;

        $query = JournalEntryLine::where('account_id', $statementLine->bank_ledger_id)
            ->whereNull('reconciled_at')
            ->where('is_deleted', false);

        if ($isWithdrawal) {
            $query->where('credit_amount', $amount);
        } else {
            $query->where('debit_amount', $amount);
        }

        // Apply a date proximity window of ±15 days
        $txnDate = Carbon::parse($statementLine->transaction_date);
        $startDate = $txnDate->copy()->subDays(15)->toDateString();
        $endDate = $txnDate->copy()->addDays(15)->toDateString();

        $query->whereHas('entry', function($q) use ($startDate, $endDate) {
            $q->whereBetween('voucher_date', [$startDate, $endDate]);
        });

        return $query->with('entry')->get()->map(function($line) use ($statementLine) {
            // Compute a simple matching score/rank (1-100)
            $score = 50; // base score for matching amount and date window
            
            $entryDate = Carbon::parse($line->entry->voucher_date);
            $daysDiff = abs($entryDate->diffInDays(Carbon::parse($statementLine->transaction_date)));
            
            // Deduct score for date distance
            $score -= ($daysDiff * 2);

            // Boost score if reference matches
            if ($statementLine->reference_no && $line->entry->voucher_number) {
                if (stripos($line->entry->voucher_number, $statementLine->reference_no) !== false || 
                    stripos($statementLine->reference_no, $line->entry->voucher_number) !== false) {
                    $score += 40;
                }
            }

            return [
                'id' => $line->id,
                'voucher_number' => $line->entry->voucher_number,
                'voucher_date' => $line->entry->voucher_date->toDateString(),
                'narration' => $line->line_narration ?: $line->entry->narration,
                'debit' => (float)$line->debit_amount,
                'credit' => (float)$line->credit_amount,
                'score' => max(0, min(100, $score)),
            ];
        })->sortByDesc('score')->values();
    }

    /**
     * Map headers to index positions.
     */
    protected function detectHeaderIndices(array $headers): array
    {
        $indices = [
            'date' => 0,
            'description' => 1,
            'reference' => 2,
            'debit' => 3,
            'credit' => 4,
            'balance' => 5
        ];

        foreach ($headers as $index => $header) {
            $h = strtolower(trim($header));
            if (str_contains($h, 'date') || $h === 'dt') {
                $indices['date'] = $index;
            } elseif (str_contains($h, 'description') || str_contains($h, 'narration') || str_contains($h, 'particular') || str_contains($h, 'remark')) {
                $indices['description'] = $index;
            } elseif (str_contains($h, 'ref') || str_contains($h, 'cheque') || str_contains($h, 'chq') || str_contains($h, 'txnid') || str_contains($h, 'txn id')) {
                $indices['reference'] = $index;
            } elseif (str_contains($h, 'debit') || str_contains($h, 'withdrawal') || str_contains($h, 'dr') || str_contains($h, 'out') || $h === 'debit amount') {
                $indices['debit'] = $index;
            } elseif (str_contains($h, 'credit') || str_contains($h, 'deposit') || str_contains($h, 'cr') || str_contains($h, 'in') || $h === 'credit amount') {
                $indices['credit'] = $index;
            } elseif (str_contains($h, 'balance') || $h === 'bal') {
                $indices['balance'] = $index;
            }
        }

        return $indices;
    }

    /**
     * Clean and parse a decimal amount.
     */
    protected function parseAmount(string $value): float
    {
        $cleaned = preg_replace('/[^0-9\.\-]/', '', $value);
        return (float)($cleaned ?: 0.0);
    }

    /**
     * Parse date from various formats.
     */
    protected function parseDate(string $value): ?string
    {
        $val = trim($value);
        $formats = [
            'Y-m-d',
            'd-m-Y',
            'm-d-Y',
            'd/m/Y',
            'm/d/Y',
            'Y/m/d',
            'd-M-Y',
            'd M Y'
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $val)->toDateString();
            } catch (\Exception $e) {
                // Continue trying other formats
            }
        }

        // Try standard strtotime fallback
        try {
            $ts = strtotime($val);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        } catch (\Exception $e) {}

        return null;
    }
}
