# Opening balances

Open **Finance → Opening Balances** (also linked from Journal Entry).

1. Select the active plant and the date from which you will use the portal's balances.
2. Enter patron balances in the Patron tab, explicitly selecting their accounting ledger. The same entry updates both the patron and the ledger; do not enter the control ledger total again.
3. Enter independent balances (bank, cash, capital, etc.) in the Ledger tab.
4. For bulk entry, download the CSV template. Use the ledger/patron codes shown in the selectors. Leave `patron_code` empty for independent ledger rows. Use `Dr` or `Cr` and unsigned amounts with at most two decimal places (no thousands separators). Export Excel files as CSV first. Maximum: 2,000 rows / 2 MB.
5. Preview the CSV, then apply it to replace the form rows. Importing itself never writes accounting entries.
6. Select an existing balance-sheet Opening Balance Clearing ledger if the debit/credit totals differ. Create that ledger through the normal ledger screen if needed. Reconcile its balance to zero when the complete trial balance is entered.
7. Save the draft, review ledger totals, and post. Drafts have no accounting effect.
8. Verify Patron Statement and Ledger Report from the cutover date. Opening entries are dated **one day before cutover** and use the existing report calculation. Voucher filters affect period transactions, while the ledger opening carries the complete account balance.

There is one migration setup per plant, not a new opening every financial year. Existing history already carries forward automatically. Posting rejects selected ledgers with earlier non-opening journal history, and patrons with earlier invoices/payments, because importing their full balance would double-count that history.

Posted batches are locked. **Correct opening setup** requires a reason and the journal-delete permission. It posts an equal/opposite journal on the original date and reopens the draft; save and post the corrected values. This changes historical opening balances. Original and reversal journals remain visible in Posting History. Concurrent/stale edits return a conflict, and retrying a successful post does not create another entry.

Permissions reuse `JOURNAL_ENTRY.VIEW`, `JOURNAL_ENTRY.CREATE` (draft, import, post), and `JOURNAL_ENTRY.DELETE` (reverse). Server-side plant validation applies to every ledger and patron reference.

CSV example (replace codes with those in your plant):

```csv
ledger_code,patron_code,side,amount,reference
DEBTORS,CUST001,Dr,10000.00,Customer opening
CREDITORS,VEND001,Cr,7000.00,Supplier opening
BANK,,Dr,25000.00,Bank book opening
```

This feature imports account balances. It does not create legacy invoices, tax entries, ageing buckets or invoice-level payment allocations. Bill-wise opening import requires a separate outstanding-document and allocation workflow.

## Installation and verification

Apply only the new migrations if other unrelated migrations are pending:

```sh
php artisan migrate --path=database/migrations/2026_09_19_120000_create_opening_balance_batches.php --force
php artisan migrate --path=database/migrations/2026_09_19_120100_add_opening_balances_menu.php --force
npm run build
```

Focused SQLite checks (no live accounting records are changed):

```sh
php tests/Standalone/opening-balances.php
php vendor/phpunit/phpunit/phpunit --filter OpeningBalanceTest
```

The standalone suite runs without Composer development dependencies. The PHPUnit suite additionally exercises HTTP permissions and CSV import endpoints when development dependencies are installed.
