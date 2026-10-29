<!--
Author: ragul-onemodo
Created: 2026-10-18 21:57:32 Asia/Calcutta (UTC+05:30)
-->

# Accounting & GST integration delivery

This document supersedes the accounting-integrity, Tally queue, validation and migration limitations in `accounting-gst-finalization.md`. That document remains the detailed normalized GSTR-2B, classification, policy, maker/checker and worksheet contract. The integration extends the portal's existing books; it does not install another application or database.

## Stack and starter assessment

The portal uses Laravel 12, PHP 8.3, MySQL, Sanctum/Jetstream session authentication, Spatie permissions, Inertia 2, Vue 3, Tailwind, PrimeVue, Heroicons and Vite. Existing Finance modules include ledger masters, journal entries, invoices/bills/notes, receipts/payments, opening balances, bank reconciliation and GST reports. The web routes retain the existing authenticated, verified, session and CSRF middleware. Deployment uses Composer, Artisan migrations/seeders and the Vite production build. The existing GitHub workflow used unsupported PHP 8.0 for this project; it now uses 8.3 and includes a separate accounting job.

The attached ZIP was extracted outside the repository into a temporary directory and inspected. It is also Laravel, but provides a backend-only, company/token-scoped SQLite ledger. It has no accounting UI and no native GSTN import adapter. Reused/adapted concepts include decimal-safe money, balanced postings, idempotency, reversals, ledger/trial balance, immutable evidence, normalized 2B matching, separately reviewed tax facts/policies, frozen 3B worksheets and conservative Tally XML delivery. Company tokens, standalone company/account/journal tables, the welcome page and SQLite-specific production configuration were not installed. `plant_id` replaces the starter's company scope throughout the portal integration.

## Implemented workflows

| Requirement | Portal implementation |
|---|---|
| Dashboard | `/finance/accounting`, with real posted income/expense/profit, account balances, trial totals, classified receivable/payable balances, per-head GST ledger movements, recent journals and persisted bank reconciliation counts. Unclassified control totals are explicitly unavailable, not invented zeros. |
| Chart of accounts | Reuses `mm_ledgers` and existing plant account groups. Create/classify/update/deactivate through a transactional service; plant/code uniqueness, parent ownership/cycle checks, explicit asset/liability/equity/income/expense types, tax/control classifications and restrictions on used accounts. Existing category roots provide reporting classifications where recognized; unknown roots are disclosed as incomplete. |
| Opening balances | Balanced draft/post journals against verified equity/control ledgers; also preserves existing bulk/individual opening workflows. Newly posted opening entries are sealed. Retirement of individual opening journals creates an offsetting reversal. No fabricated opening balances. |
| Journals | Balanced multi-line drafts, controlled edits/deletion of drafts, separate posting permission, references/dates/actor, month controls, exact four-place arithmetic, transactional posting, immutable posted headers/lines, idempotent request keys and unique concurrent numbering. A reversed status is derived from the separate reversal record; the original header remains intact. |
| Corrections | Reverse once, retain original and audit, replace under a new key. Invoice/bill/note, purchase-order and payment adapters retain posted versions, automatically reverse changed source postings and append replacements. Source cancellation creates a reversal instead of deleting posted financial history. |
| Ledger/trial | Opening, period debit/credit, closing and account-wise running balances. Date/account/reference filters, stable date/id ordering, pagination, totals and CSV export. A reference filter does not remove intervening activity from running balances. Drafts are excluded from both the new reports and the legacy ledger report. |
| Financial reports | Trial balance/account activity, journal register, period profit and loss, and a balance sheet as of the end date. Cumulative income/expense balances are presented as retained result, not invented year-end closing journals. Missing account classifications and out-of-balance data are disclosed. |
| GSTR-2B | Starter-supported normalized JSON, 10 MiB/20,000 document limits, recipient/period/provenance checks, a read-only preview, row-level errors, per-head totals, duplicate-row warnings, exact-byte duplicate-import detection and immutable import revisions. Invalid rows prevent import; duplicate rows are retained for exception review. |
| GST matching/review | Exact/exception and candidate/amount differences, supplier reference confirmation, per-head matching, current source fingerprints, supported ITC classifications, independent reviews, policy windows and stale worksheet detection. Matching alone never confers ITC entitlement. |
| GSTR-3B | Independently reviewed, frozen internal working papers using validated classifications, source hashes and audit evidence. This does not file a return or claim GSTN acceptance. |
| Audit | Append-only accounting events with actor, payload, previous digest and digest, plus a verification/checkpoint endpoint. Existing GST evidence retains its separate append-only chain. Keep checkpoints outside the application's write authority. |
| Tally | Per-plant enable/company configuration, explicit listener reachability check, immutable frozen outbound XML queue, unique journal/GUID identities, recorded attempts/receipts, confirmed/failed/uncertain states, manual evidenced resolution before retry, and existing independently reviewed mapped inbound staging/posting. Transport and generic Journal XML adapters are implemented; actual target-company acceptance remains external. |
| UI | Existing AppLayout/subnavigation and design tokens, permission-aware actions, filters/search/pagination, form errors, loading/empty states and confirmation dialogs. Existing manual-journal navigation opens the integrated workspace once migrated. |

Invoice/bill recalculation now preserves the chosen round-off using integer paise, and percentage discounts post their computed monetary value rather than the rate. Sales-register sorting includes document date, document ID and item ID tie-breakers. Both previously failing accounting regression cases now pass.

Amounts in accounting requests and responses are decimal **strings**, with up to four places, matching the existing ledger precision. Authoritative service calculations use bounded integer ten-thousandths; source invoice/tax/adjustment conversion to paise uses decimal-string arithmetic. The Tally adapter is two-place INR and refuses exports whose per-line rounded amounts do not reconcile; it does not silently make a rounding journal. GST classifications retain the starter's integer-paise contract. No universal GST rate or automatic statutory eligibility rule was introduced.

## Code and storage

New modules:

- `app/Http/Controllers/AccountingWorkspaceController.php` — authenticated accounting routes and CSV downloads.
- `app/Services/Finance/AccountingMoney.php`, `AccountingWorkspace.php`, `AccountingReports.php` — exact money, accounts, drafts/posting/reversal, month control, reports and audit.
- `app/Services/Finance/SourceJournalService.php` — immutable source posting, correction and cancellation adapter.
- `app/Services/Finance/AccountingTally.php` — configuration, probe, durable queue, delivery and evidenced recovery.
- `app/Services/Gst/GstImportPreview.php` — full-file and row validation without persistence.
- `app/Console/Commands/AccountingPreflight.php` — read-only readiness checks.
- `resources/js/Pages/Accounting/Index.vue` — integrated Finance workspace.
- `database/seeders/AccountingWorkspaceSeeder.php` — navigation and permissions; invoked by the GST seeder.
- `tests/Feature/AccountingWorkspaceTest.php`, `tests/Support/accounting-concurrent-worker.php`, `tests/Frontend/accounting.test.mjs` — domain/API, MySQL concurrency and frontend workflow checks.

Adapted existing modules include ReportRepository (stable register ordering), AccountingPostingService, Invoice's decimal conversion and cancellation, Payment posting/cancellation, PostsToAccounting, manual JournalEntryController, LedgerController/request validation, bank quick posting, opening-balance services, note/dispatch cancellation, the legacy ledger report, GstAccess, GstWorkspace, GstComplianceController/Index, TallyJournalExport, web routes, database/GST configuration, example environment and CI. Existing unrelated PDF changes in the working tree were preserved.

The additive migration `2026_10_18_211203_create_accounting_workspace.php` creates `mm_accounting_accounts`, `mm_accounting_periods`, `mm_accounting_journals`, `mm_accounting_events`, `mm_accounting_tally` and `mm_accounting_settings`. It adds plant/code and composite plant/id indexes to existing ledger/journal tables, composite foreign keys for metadata ownership, immutable-event/header/line/seal triggers, and a database seal check for balanced line/header amounts and ownership. Existing ledger/journal rows are retained. New financial tables explicitly use InnoDB; MySQL connection defaults now select InnoDB for future tables rather than inheriting this WAMP server's MyISAM default. Existing tables are never converted implicitly by this migration.

The earlier additive `2026_10_17_141500_create_gst_compliance_tables.php` remains required. Both financial migrations refuse rollback once evidence/history exists. Revert application code while retaining compatible financial tables; never drop audit history to roll back a release. Empty migrations can be rolled back in disposable staging. Historical journals that predate this release are retained; they are adopted/sealed when corrected or reversed. The application does not claim that a database administrator cannot rewrite history or remove triggers.

## Permissions and endpoint contract

`ACCOUNTING.VIEW` is required in addition to every action grant. Active-plant membership is checked against the matching entity/plant assignment and the portal's direct permissions; restrictive plant assignments take precedence. The established system-administrator bypass remains. Frontend plant/user IDs never select the security context. A supplied `plant_id` asserts the selected plant; a stale tab receives 409. Existing endpoints retain their ordinary authentication middleware. Accounting requests are rate limited.

| Grant | Operations |
|---|---|
| `ACCOUNTING.VIEW` | Workspace, reports, journal detail, ledger and audit verification |
| `ACCOUNTING.CREATE` | Create/classify accounts and balanced drafts |
| `ACCOUNTING.UPDATE` | Update account classifications and drafts; delete drafts |
| `ACCOUNTING.POST` | Post a balanced draft |
| `ACCOUNTING.REVERSE` | Create a controlled reversal |
| `ACCOUNTING.ADMIN` | Open/close accounting months; configure Tally company |
| `ACCOUNTING.EXPORT` | Download financial CSV reports |
| `ACCOUNTING.TALLY` | Queue/send/probe/reconcile outbound jobs |

The additive seeders do not assign these rights to ordinary users. Assign only the required grants through the existing role/permission administration. Existing Finance parent navigation may require `ACCOUNT.VIEW`. GST preparation/review/export and Tally inbound rights remain separate `GST_COMPLIANCE.*` grants documented in the earlier guide; maker/checker restrictions remain in force.

Base `/finance/accounting`:

| Method | Path | Body/query |
|---|---|---|
| GET | `/`, `/data` | `from`, `to` ISO dates; `search`, `page`, optional context assertion `plant_id` |
| POST / PUT | `/accounts`, `/accounts/{id}` | Existing plant group `account_type_id`, optional existing `ledger_id` for classification, `code`, `title`, `type`, `parent_id`, `tax_class`, `control`, `active` |
| POST / PUT | `/journals`, `/journals/{id}` | `request_key`, ISO `date`, `reference`, `narration`, `lines[{account_id,debit,credit,narration}]`; debit/credit decimal strings |
| GET / DELETE | `/journals/{id}` | Detail / draft deletion |
| POST | `/journals/{id}/post` | Optional context assertion; repeat is idempotent |
| POST | `/journals/{id}/reverse` | New `request_key`, ISO `date`, evidence `reason` |
| GET | `/ledger` | `account_id`, date range, `search`, `page` |
| GET | `/export` | `report=trial|profit|balance|ledger|journals`, dates; ledger requires `account_id` |
| GET | `/audit` | Verify append-only chain; returns validity/count/checkpoint |
| POST | `/periods` | `period=YYYY-MM`, `closed`, evidence `reason` |
| POST | `/tally/config` | `tally_company`, `tally_enabled` |
| POST | `/tally/connection` | Read-only listener request; does not verify a company or synchronize a voucher |
| POST | `/tally/journals/{id}` | Explicitly queue a posted journal |
| POST | `/tally/jobs/{id}/send` | Send a pending frozen payload once |
| POST | `/tally/jobs/{id}/resolve` | `action=confirmed|retry_absent`, evidence `reason` after inspecting GUID/content and stopping old workers |

`POST /finance/gst-compliance/imports/preview` accepts the same multipart JSON file and reporting period as import. Successful preview returns `valid`, row errors/warnings, totals, document count, up to 200 preview rows, duplicate rows and exact-import status. It does not write a period, import or audit event. Import repeats server validation even if preview was bypassed. Unknown native GSTN formats require an explicit reviewed adapter, consistent with the starter's contract.

Validation failures return 422, permission failures 403, wrong-scope/missing IDs 404, stale context/closed periods/idempotency conflicts 409. Existing JSON error handling is preserved. CSV cells that could execute formulas are escaped.

## Local and staging procedure

1. Install the existing project's dependencies with PHP 8.3+, Composer and Node 22. Preserve `.env` and its secrets. Do not run the Composer `setup` script against an existing deployment: it includes automatic migrations.
2. Run `php artisan accounting:preflight` against a backed-up staging clone first. It checks financial storage engines, duplicate plant/code groups and posted journal header/line balances without changing data. Review duplicates and map/renumber them through an audited data migration; do not delete ledgers or financial lines to satisfy uniqueness.
3. Set `GST_TALLY_NAMESPACE` once to a stable, installation-specific value and retain it across domain changes/restores. Optional `GST_TALLY_URL` must identify the private listener reachable from this application host; keep it unset when Tally delivery is disabled. Never expose a desktop XML listener publicly. Configure exact ledger overrides in `config/gst_compliance.php` only from verified Tally masters.
4. After the preflight blockers are resolved on staging, back up and test restore. Apply the additive migrations:

```powershell
php artisan migrate --path=database/migrations/2026_10_17_141500_create_gst_compliance_tables.php
php artisan migrate --path=database/migrations/2026_10_18_211203_create_accounting_workspace.php
php artisan db:seed --class=GstComplianceSeeder
npm run build
php artisan optimize:clear
```

5. Assign permissions to actual users, separate GST preparation/review identities, select the correct plant, and open `/finance/accounting`. Verify real opening balances and existing account classifications. Do not initialize sample balances in live books.
6. For local development run `php artisan serve` and `npm run dev` in separate terminals, or use the existing WAMP virtual host with its normal PHP configuration. The existing queue/reverb processes remain governed by the portal's deployment setup; outbound queue delivery is explicit through the Tally screen and does not claim an installed background worker.
7. Run the tests below. On staging, also perform an authenticated browser walkthrough at desktop/mobile widths, import a reviewed taxpayer statement with known totals, compare reports with independently reconciled books, and complete Tally acceptance using a backed-up test company before enabling sending.

```powershell
php -d memory_limit=768M vendor/bin/phpunit tests/Feature/AccountingWorkspaceTest.php tests/Feature/GstComplianceTest.php tests/Feature/AccountingPostingSafetyTest.php
$env:ACCOUNTING_TEST_MYSQL='1'
php -d memory_limit=768M vendor/bin/phpunit tests/Feature/AccountingWorkspaceTest.php
Remove-Item Env:ACCOUNTING_TEST_MYSQL
npm run test:accounting:frontend
npm run build
```

The MySQL test mode creates random `codex_accounting_test_<hex>` databases, uses InnoDB fixtures, and drops only those verified disposable databases. The configured identity needs CREATE/DROP DATABASE privileges for this test mode. It never migrates the configured business database. Concurrent workers refuse any database name outside that disposable prefix. SQLite tests assert in-memory configuration. SQLite is a test adapter; four-place large-value production persistence relies on MySQL DECIMAL/InnoDB.

## Acceptance results and blockers

Results recorded on 2026-10-18, using the actual commands and logs below. The configured business database was not used as an automated-test fixture.

| Check | Actual result |
|---|---|
| Accounting/GST/posting safety | **57 tests, 297 assertions; no failures/errors, one expected concurrency skip on SQLite**. The skipped scenario was executed on MySQL. |
| Real MySQL/InnoDB | **25 tests, 129 assertions, all passed**. Includes independent concurrent processes, shared-key idempotency, distinct voucher numbers, database triggers, failed-write rollback, source corrections, protected rollback and GST evidence triggers. |
| Existing invoice/bill/note/purchase/register workflows | **61 tests, 789 assertions, all passed** after the source-adapter, round-off and register-ordering fixes. |
| Frontend | **7 tests passed**: compiler validation, exact decimal submission/display, stale-plant handling, ledger filters, reversal request identity and runtime rendering of all eight accounting sections in their empty states. Tests use real Vue with a stubbed existing layout/network; they are not a live authenticated browser walkthrough. |
| Production assets | `npm run build` passed. Existing Browserslist-age, large-chunk and empty-vendor-chunk notices remain nonfatal. |
| Static checks | PHP syntax checks, focused Laravel Pint and `git diff --check` passed. |
| Broad legacy-suite snapshot | **800 tests, 3,500 assertions, 146 errors, 94 failures, 8 skipped, 6 PHPUnit deprecations**. This run preceded the final two accounting fixes and percentage-discount test; affected accounting workflows were rerun successfully as listed above. The application-wide suite is not certified green. |
| CI | Added separate accounting SQLite/MySQL/frontend/build checks and corrected PHP 8.0 to 8.3. The remote GitHub workflow was not executed by this task. |

The broad command was `php -d memory_limit=1G vendor/bin/phpunit --filter '^(?!.*test_whatsapp_certificate_failure_returns_safe_specific_error).*$' --log-junit tmp/accounting-suite-final.xml`. Two inherited instances of that pre-existing WhatsApp certificate-error test were excluded after terminating earlier runs without a summary; its isolated attempt also did not complete normally. Other broad failures include removed contact-type/invoice-status routes, stale account/entity/payment fixtures, missing opening-balance audit fixtures/import routes, and plant-initialization fixtures referencing `mm_patrons.aadhar_number`. These are reported as test/legacy defects, not external service blockers or successful checks. The two accounting report failures were separately reproduced with the original Git posting classes, then fixed and included in the successful 61-test rerun.

Local evidence is retained in `tmp/accounting-focused.log`, `tmp/accounting-mysql.log`, `tmp/accounting-regression.log`, `tmp/accounting-frontend-tests.log`, `tmp/accounting-build.log`, `tmp/accounting-suite-final.xml`, `tmp/accounting-suite-final.log`, `tmp/accounting-baseline-verification.log`, `tmp/accounting-excluded-test.log` and `tmp/accounting-preflight.log`. These test artifacts are not production records.

### Remaining data and external acceptance blockers

If the accounting tables are absent or installation is partial, `/finance/accounting` now shows an authenticated, plant-scoped setup screen instead of querying missing tables. Accounting JSON/export/write endpoints return HTTP 503 with preflight guidance; authorization and stale-plant checks still take priority. This guard does not install schema or resolve the duplicate-code blocker. Refresh the setup screen after the reviewed migration succeeds. `accounting:preflight` checks all six accounting workspace tables and returns failure while any required schema is pending.

For the reported `mm_accounting_accounts` error, the read-only check on 2026-10-18 confirmed all six accounting tables are missing, 53 duplicate plant/code groups remain, financial tables use InnoDB, and posted header/line balance review found zero discrepancies. The existing migration refuses duplicates before creating any accounting tables. Do not create a standalone empty table or remove this guard: complete the audited code mapping and then apply the full migration using the staging procedure above.

After adding the setup guard, the focused accounting/GST/posting-safety suite was rerun: **58 tests, 409 assertions, no failures/errors, one SQLite concurrency skip**. The MySQL run passed **26 tests, 240 assertions**, including each missing-table scenario, denied unauthorized actions, stale-plant checks, blocked writes/exports, and recovery after schema restoration. All **8 frontend tests** passed, including rendering the setup screen with the active plant and migration guidance. The production asset build also passed after this fix. These results supersede the corresponding counts above; they do not change the business-data migration blocker or the broad legacy-suite findings.

1. **Duplicate ledger codes:** the latest read-only configured-database preflight found **53 duplicate plant/code groups**. The new uniqueness migration deliberately refuses these records. A data owner must approve an audited mapping/renumbering; no ledgers or financial lines were merged, deleted or renumbered by this task. Posted journal header/line balance review count was **zero** in the same check.
2. **Migration/access acceptance:** accounting workspace tables remain pending in that database; GST tables are present in the latest check. Run the staging procedure only after resolving duplicates, then assign the actual role grants and perform authenticated desktop/mobile browser acceptance. Initial checks detected MyISAM (including the server default); the latest checked financial tables report InnoDB. This task did not execute a live engine conversion. The migration retains its engine checks and new-table InnoDB settings.
3. **Tally acceptance:** `GST_TALLY_URL` is unset. Supply the private listener reachable from the portal host, exact test-company name, stable namespace and verified ledger mapping. Confirm actual GUID/content, receipts, inbound mapping and correction behavior in a backed-up test company. HTTP transport is implemented and tested with controlled responses; no live synchronization is claimed.
4. **GST business evidence:** provide the actual reviewed normalized statement/original download, recipient registration details, supplier references, receipt/eligibility evidence, effective policy approvals and separate preparer/reviewer identities. Compare worksheet totals and registration-wide scope with the actual portal before filing separately. No real taxpayer statement or GSTN integration was supplied or exercised.
5. **Existing test debt:** the unrelated application-wide legacy failures above remain an acceptance issue. The focused integration and critical financial workflows pass; the whole application is not represented as fully regression-clean.


No production deployment, business-data cleanup, configured-database migration, real GSTN filing or live Tally delivery was performed by this task. The live-data migration and authenticated browser acceptance remain gated on the preflight/data review. A return reviewer must supply actual supplier evidence, eligibility decisions, effective policies and registration-wide consolidation where required. Native GSTN download formats, specialized statutory/inventory Tally vouchers and automatic statutory ITC adjudication are outside the supplied starter's interfaces; do not represent their absence as a successful integration with those external systems.
