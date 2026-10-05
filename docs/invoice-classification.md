# Invoice document classification

`mm_invoices` now has two canonical fields:

| Creation flow | document_type | document_source |
| --- | --- | --- |
| Sales generated from Dispatch, including consolidated dispatches | INVOICE | DISPATCH |
| Manual sales invoice | INVOICE | MANUAL |
| Purchase / Stock-In or consolidated purchase orders | BILL | PURCHASE_STOCKIN |
| Manual purchase bill | BILL | MANUAL |

`invoice_type` now stores `Invoice` / `Bill`; `document_type` retains the uppercase `INVOICE` / `BILL` values. The `invoice_label` field remains for existing integrations. Requests accept old sales/purchase aliases and normalize them before validation. Reports, dashboards, AI finance tools, opening-balance checks and purchase bill relationships read both historical and canonical type values. Model save events populate and synchronize the canonical fields. New request fields are optional for older clients, have enumerated validation, and reject incompatible document/source pairs. Sales and purchase controllers determine the source from the actual creation flow. Invoice/bill JSON responses include the new stored fields. The outstanding API accepts `document_type` and `document_source` filters; numbering endpoints accept `document_type` alongside the existing `invoice_type` parameter.

Existing credit/debit notes retain their legacy type and have null canonical fields; they are not converted into ordinary invoices or bills. Backfill includes soft-deleted rows, consults dispatch-status and purchase-order billing links, and preserves legacy fields, amounts, invoice numbers, journals, and timestamps. Unknown labels on ordinary invoices/bills default to MANUAL when no source link is available. The migration uses the query builder and does not trigger model or accounting events.

## Migration and asset steps

Both classification migrations have been applied to the local application database. Verification found 38 `Invoice`, 10 `Bill`, and 2 unchanged `credit_note` records. For other environments, apply these migrations in order:

```powershell
php artisan migrate --path=database/migrations/2026_10_03_120000_add_document_classification_to_invoices.php
php artisan migrate --path=database/migrations/2026_10_03_130000_normalize_invoice_type_values.php
npm.cmd run build
```

Alternatively, use the normal `php artisan migrate` deployment command if all pending migrations should be applied. The classification migration adds nullable enum columns, an index over plant/type/source, and backfills existing records in chunks. Its rollback drops only the new index and columns. The separate type migration normalizes commercial aliases, including deleted records, without altering notes, amounts, numbering, timestamps or journals. Its rollback restores `sales` / `bill` categories; exact historical aliases/capitalization are not retained. Roll back the application code together with the migrations because the updated model writes the new columns.

## Verification

- Focused PHPUnit coverage passed: 11 tests, 94 assertions covering persistence through save events, bill numbering, all four classifications, legacy and canonical payloads, edit behavior, credit notes, validation, migration backfill/rollback, accounting context, purchase relationships and bulk export queries on isolated in-memory SQLite.
- The standalone purchase receipt billing regression passed.
- Changed PHP files passed syntax checks; `git diff --check` passed.
- The Vite production build passed with output directed to a temporary directory. Rebuild the normal assets during deployment; the build reported existing Browserslist freshness and chunk-size warnings.
- The full suite was run using a temporary PHPUnit 11 PHAR and temporary Mockery/Faker dependencies. Remaining suite failures and the module-specific checks are documented in `docs/invoice-module-test-report.md`.

## All changed files

- `.gitignore`: allow the new focused feature test to be tracked.
- `app/Support/InvoiceClassification.php`: legacy mapping and classification constraints.
- `app/Models/Invoice.php`: fillable fields, save normalization, source generation and bill numbering.
- `app/Http/Requests/Concerns/ValidatesInvoiceClassification.php`: shared request validation and canonical-only type preparation.
- `app/Http/Requests/StoreInvoiceRequest.php`: accept and validate the new fields.
- `app/Http/Requests/UpdateInvoiceRequest.php`: validate edits and support canonical-only type input.
- `app/Http/Controllers/InvoiceController.php`: manual/dispatch classification and API filters/numbering.
- `app/Http/Controllers/BillingController.php`: manual/purchase classification.
- `app/Http/Controllers/DispatchController.php`: explicit dispatch invoice classification.
- `app/Http/Controllers/PurchaseOrderController.php`: explicit receipt bill classification; existing local edits preserved.
- `database/migrations/2026_10_03_120000_add_document_classification_to_invoices.php`: enum schema, index, backfill and rollback.
- `database/factories/InvoiceFactory.php`: compatible invoice/bill manual defaults; model events populate canonical values.
- `resources/js/Pages/Invoices/createinvoiceform.vue`: manual and consolidated dispatch form fields.
- `resources/js/Pages/Invoices/components/InvoiceIndexList.vue`: display document type/source.
- `resources/js/Pages/Billing/CreateBillingForm.vue`: manual and consolidated purchase form fields.
- `resources/js/Pages/Billing/components/BillingIndexList.vue`: display document type/source.
- `tests/Feature/InvoiceClassificationTest.php`: focused regression coverage.
- `docs/invoice-classification.md`: this implementation and deployment report.

The cross-module follow-up also updates:

- `app/Ai/Tools/ModoFinance.php` and `app/Ai/Tools/Modormc.php`: recognize canonical types in finance queries.
- `app/Http/Controllers/ERPDashboardController.php`: invoice/bill dashboard queries.
- `app/Http/Controllers/BulkDocumentReportController.php`: classify Invoice rows correctly in bulk previews.
- `app/Http/Controllers/PrintController.php`: select bill print settings correctly.
- `app/Models/PurchaseOrder.php`: invoice-type aliases and case-compatible purchase labels in bill/history relationships.
- `app/Repositories/ReportRepository.php`: sales register queries.
- `app/Services/BulkDocumentZipExportService.php`: distinguish invoice and bill archive filenames.
- `app/Services/OpeningBalanceService.php`: identify sales invoice history.
- `app/Services/PrintDataFormatter.php`: bill/invoice preview, title, terms and settings selection.
- `app/Services/Reports/BulkDocumentQuery.php`: filters and journal reference lookup for old and canonical types.
- `app/Services/Reports/CustomerOutstandingReportService.php`: invoice filters.
- `app/Services/Reports/DeletedReportService.php`: invoice/bill filters.
- `app/Services/Reports/Gstr1ReportService.php` and `app/Services/Reports/Gstr3bReportService.php`: canonical types and approved/paid status variants.
- `app/Services/Reports/OverallReportService.php`: invoice/bill filters.
- `app/Services/Reports/RegisterAddressGstFormat.php`: invoice address/GST export lookup.
- `app/Services/Reports/TdsCertificateReportService.php`: invoice filters.
- `database/seeders/InvoiceSeeder.php`: canonical type defaults.
- `database/migrations/2026_10_03_130000_normalize_invoice_type_values.php`: normalize existing invoice types.
- `resources/views/reports/bulk_document.blade.php`: recognize historical purchase aliases as bills.
- `tests/Standalone/register-reports.php`: bring fixture columns/address tables and identifier-column checks up to date.
- `tests/Standalone/bulk-document-pdf.php`: test canonical Invoice/Bill PDF fixtures.
- `tests/Standalone/bulk-document-zip.php`: use the current private export path.
- `docs/invoice-module-test-report.md`: full module verification results and remaining failures.
