# Invoice / Bill module verification — 3 October 2026

## Result

All ordinary invoice records now use `invoice_type = Invoice` or `Bill`. The local normalization migration was applied and a read-only database audit confirmed:

| Stored type | Records, including deleted records |
| --- | ---: |
| Invoice | 38 |
| Bill | 10 |
| credit_note | 2 |

Both `document_type` and `document_source` columns are present. Both classification migrations are recorded as applied. The type migration updated commercial names only; credit notes, amounts, numbering, dates and journals were not rewritten.

The code audit found inconsistent partial changes: modules queried Invoice/Bill while the model still saved sales/bill, and other readers accepted sales only. These were fixed across creation, validation, relationships, reports, dashboards, AI finance queries, printing and bulk exports. Legacy aliases remain readable, and old request values are normalized before validation. Invoice source labels remain separate from document types. [The implementation report](invoice-classification.md) lists all involved files and deployment/rollback commands.

## Passing checks

| Check | Result |
| --- | --- |
| Focused classification suite | 11 tests, 94 assertions passed |
| All existing unit tests | 43 tests, 118 assertions passed |
| Existing purchase inward controller suite | 9 tests, 54 assertions passed |
| Purchase receipt billing | Partial receipts, quantities, charges, tax and duplicate prevention passed |
| Purchase conversion billing | Quantity/UOM precision, partial receipts, void/rebill and settings passed |
| Bulk document filters | 26 checks and 107 matching dropdown options passed |
| Bulk selection job | Selected invoice IDs retained; expired payload fails safely |
| Bulk PDF / ZIP | Canonical Invoice/Bill fixtures produced a 10-page PDF and 13 separate PDFs in a ZIP; startup failure state passed |
| Register address / GST exports | Sales summary/detail/legacy addresses, shipping, truck, GST, roundoff, endpoint validation and cell types passed |
| Report permissions | 35 permissions, migration grants, tenant scope, denials and private export checks passed |
| Batch e-invoice backend | 24 permission, plant-isolation, error-handling and IRN guard checks passed; no live gateway submissions |
| Frontend production build | Passed; temporary output directory, existing bundle-size/Browserslist warnings |
| PHP syntax / whitespace checks | Changed PHP files passed syntax checks; diff whitespace checks passed |

Read-only smoke tests also completed successfully against actual application data for both plants with invoice records:

- Sales register and purchase register generation.
- Customer outstanding and overall report generation.
- GSTR-1 and GSTR-3B generation.
- Deleted document reports.
- Bulk invoice/bill queries.
- Invoice and bill print data preparation, including verification of the correct purchase-bill flag.

This validates successful execution and document-category selection. It does not independently certify every report amount, every browser interaction, or external e-invoice gateway behavior. Services that catch internal errors still require their own correctness assertions.

## Full-suite result and remaining failures

The complete configured PHPUnit suite was executed with an isolated in-memory SQLite database, temporary PHPUnit 11, Mockery and Faker dependencies, and a 1 GiB PHP memory limit. The project vendor directory was not changed.

**560 tests: 324 passed, 151 errors, 78 assertion failures, 7 skipped; 1,473 assertions.** The full suite is not clean.

Relevant existing integration suites:

| Suite | Tests | Errors | Assertion failures |
| --- | ---: | ---: | ---: |
| InvoiceControllerTest | 14 | 0 | 3 |
| InvoiceTest | 3 | 3 | 0 |
| PurchaseOrderControllerTest | 13 | 0 | 2 |
| PurchaseOrderInwardControllerTest | 9 | 0 | 0 |

Observed causes include:

- Existing tests insert removed invoice fields such as `vendor_id` / `customer_id`, and some use incomplete tax-ledger mappings. The invoice store integration test reaches accounting but fails with a tax mismatch and no configured fallback tax ledger.
- A purchase bill integration assertion still expects `invoice_type = bill` rather than the new `Bill` value.
- Other suites reference missing `App\Models\WorkOrder`, missing `mm_users` fixture tables, removed patron columns, and incomplete foreign-key fixtures.
- Some suites rely on random factory types or outdated endpoint behavior instead of supplying the intended invoice/bill type.
- The standalone register-report script reaches its late sample-layout assertions but fails because the current sample export does not contain the expected `Payment Type` column. Its fixture schema and identifier-column check were updated to reach this test.
- The standalone outstanding-discount script fails the discount-only patron inclusion assertion. The opening-balance standalone script has a missing `mm_users` foreign-key fixture.

These failures are recorded, not treated as passing checks. No baseline comparison was performed to prove that every remaining failure predates this change. The focused tests specifically cover the type migration and the regressions fixed in this follow-up.

Detailed local full-suite output and JUnit results are available in `%TEMP%/portal-all-module-tests.log` and `%TEMP%/portal-all-module-results.xml`. The read-only module results are in `%TEMP%/portal-invoice-live-smoke.json`.

## Deployment

The new normalization migration `2026_10_03_130000_normalize_invoice_type_values` is already applied locally. For another environment, run it after the document-classification migration. The frontend build was verified in a temporary directory; run `npm.cmd run build` to refresh normal application assets when deploying the latest form changes.
