<!--
Author: ragul-onemodo
Created: 2026-10-17 14:45:39 Asia/Calcutta (UTC+05:30)
-->

# Accounting analysis and GST workspace implementation

Updated delivery status, immutable accounting adapters, dashboard/reports, persistent Tally queue, MySQL validation and staging blockers are documented in [accounting-integration.md](accounting-integration.md). That guide supersedes the historical implementation/validation limitations below; the normalized GST schema and working-paper workflow remain applicable.

## Existing application findings

The project is Laravel 12/PHP 8.3 with Inertia/Vue, Spatie permissions, plant/entity tenancy, existing journals/ledgers, payments, bank reconciliation, opening balances, sales/purchase registers and GST reports. The new implementation extends these modules rather than installing the earlier standalone SQLite application.

| Area | Finding | Delivered change |
|---|---|---|
| Invoice posting | Service relied on callers to open transactions; explicit ledger IDs could bypass plant ownership; voucher upsert could replace another source | Nested-safe transaction, plant lock, ownership/line/balance guards and source collision rejection |
| Rounding lines | Adjusting an existing round-off line could leave both debit and credit on one line | Net offsets within each line and omit zero lines before asserting balance |
| GSTR-3B report | Included purchase orders as ITC and guessed heads from vendor state; displayed a simple difference as net payable | Uses approved bills and stored tax splits for provisional input-tax view; warnings and link to reviewed workspace; no automatic eligibility claim |
| GSTR-2B | No import/reconciliation module found | Versioned normalized imports, exact raw checksums, indexed duplicate-aware matching and exception display |
| Return review | No independently approved source-version worksheet found | Explicit classifications, versioned rule references, maker/checker review, net ITC, POS/recipient disclosure and liability-period breakup |
| Audit | Existing model auditing and editable journals | Separate append-only GST evidence/events, hash chain, checkpoint verification and frozen worksheets that become stale after source/ledger edits |
| Tally | No ledger integration found; existing TallySheet is a print template | Balanced journal XML export, durable local delivery example, and independently reviewed inbound mapping revisions |

Legacy document editing/reposting remains supported. Existing purchase-order, payment and manual-journal posting paths have not all been rewritten into one new accounting engine. This change does not claim global database immutability or period locking across those older writers. GST approvals are point-in-time working-paper approvals; subsequent source/ledger changes mark the worksheet stale instead of blocking the old business workflows.

## Enable on the intended deployment

Review your normal database backup/deployment process first. No configured application database was migrated by this task; automated tests use isolated in-memory SQLite. The additive migration supports MySQL and SQLite and deliberately rejects other drivers until equivalent append-only triggers are implemented. MySQL trigger SQL is supplied; live MySQL execution was not exercised here. The deployment identity needs trigger privileges.

```powershell
php artisan migrate --path=database/migrations/2026_10_17_141500_create_gst_compliance_tables.php
php artisan db:seed --class=GstComplianceSeeder
npm run build
php artisan optimize:clear
```

Open **Finance → GST Reconciliation**, or `/finance/gst-compliance`. Existing top-level Finance navigation may also require `ACCOUNT.VIEW`. The additive seeder creates the menu and these permissions without assigning new rights to ordinary users:

- `GST_COMPLIANCE.VIEW`: read working papers and verify audit history.
- `GST_COMPLIANCE.IMPORT`: import a prepared statement and download its empty template.
- `GST_COMPLIANCE.PREPARE`: classify documents/adjustments, create rule versions and prepare worksheets.
- `GST_COMPLIANCE.REVIEW`: independently include/exclude classifications, approve rules and worksheets.
- `GST_COMPLIANCE.EXPORT`: download frozen worksheets/evidence and Tally journal XML.
- `GST_COMPLIANCE.TALLY_IMPORT`: stage mapped inbound Tally vouchers without posting.
- `GST_COMPLIANCE.TALLY_POST`: independently approve and post an inbound voucher; separate from GST tax review rights.

View permission and selected-plant membership are required in addition to action permissions. Active plant/entity roles and direct grants are used; a global role cannot override a restrictive plant assignment. System administrators follow the application's established bypass but still cannot review records they created. Give preparation and review to distinct actual user IDs.

## Working-paper workflow

1. Select the month and verify the plant GSTIN. The book adapter reads approved/paid `mm_invoices` including Bill/purchase aliases and linked notes. Draft/cancelled documents and bare purchase orders are excluded. It uses actual `mm_order_taxes` heads; it does not infer IGST from a supplier's current state.
2. Import the complete prepared GSTR-2B statement. Retain the original download; compare every mapped section's totals. Recomputed statements create new versions, and the last uploaded revision is the matching basis.
3. Investigate missing, duplicate, number/date, RCM and per-head amount differences. Tolerance is INR 1 per head, a review threshold with no statutory significance. Matching does not post journals or confer entitlement. In classification, enter the supplier's actual document number/date/GSTIN/kind and RCM flag. The application's generated internal bill number is deliberately not treated as the vendor's invoice number. A purchase lacking that evidence displays `supplier_reference_required`; separately evidence non-2B cases. Matching uses the current classification's supplier identity against stored book tax amounts, and never carries a reference forward after source changes without reconfirmation.
4. Classify every current book document. Choose the supported GSTR-3B row and enter the proper rupee amounts, liability month and any relevant POS/recipient/advance details. Add separate rows for reversals or disclosures. Record invoice/receipt/eligibility evidence. Manual adjustments use a stable evidence reference and explicit review.
5. A different user includes or excludes each current classification with a reason. A replacement classification requires a new review. If a source is cancelled/moved out of the month, independently exclude its old classification after checking cancellation/reversal evidence; otherwise preparation blocks it from silently disappearing.
6. Create and independently approve the applicable rule version. The shipped reference policy is explicitly `reviewed-gst-v1`, October 2025–October 2026; it contains source references and a verification date, not a universal GST rate table. Other periods require an updated effective-window policy. Unsupported historical formats need a new engine.
7. Prepare the worksheet. Resolve blockers, review every checklist item and approve through a different user. Download the frozen worksheet and evidence. New imports, rule/review/classification changes, invoice/tax edits or direct ledger changes invalidate the earlier source fingerprint. Approvals themselves do not invalidate that fingerprint.
8. Complete the portal filing/payment process separately. This output is an internal worksheet, not GSTN upload JSON. Working papers are plant scoped; a registration with several plants needs GSTIN-wide consolidation before filing. QRMP aggregation is not implemented.

### Calculations

All new-module money uses bounded integer paise. Source stored decimals are converted with decimal-string arithmetic, including half-up rounding of four-place legacy amounts. For each GST head, `4C = 4A1 + 4A2 + 4A3 + 4A4 + 4A5 - 4B1 - 4B2`. 4D1 reclaim disclosure and 4D2 unavailable-credit disclosure do not add credit by themselves. An eligible reclaim needs its proper 4A allocation as well as applicable disclosure.

3.1 and 3.1.1 sum explicitly reviewed classifications. 3.2 groups relevant interstate taxable supplies by POS and recipient class and excludes advances in this October-2025-onward engine. Liability-bearing allocations retain their declared original liability month. Table 5 and 5.1 accept reviewed inward classifications and supported interest/late-fee amounts. Signed values are retained for portal comparison; there is no simplistic automatic cash payment/setoff formula.

Rules 37/37A/42/43, blocked credit, section-16 time limits, imports/ISD, RCM cash evidence, cumulative reversal/reclaim balances, partial credit-note treatment, interest cash-ledger history, notified rates and date extensions require supported classifications and review. The engine does not adjudicate them or infer them from a matched invoice. Historical claims need a dedicated cross-period claim/reversal ledger before automating eligibility.

## Import contract and CSV adapter

The endpoint accepts normalized JSON, not arbitrary native GSTN JSON/XLSX/ZIP. A public official machine schema was not verified during this session. The explicit adapter boundary prevents unfamiliar native sections being silently ignored. The endpoint validates recipient GSTIN, month, provenance metadata and integer amounts, and preserves the exact uploaded bytes. GSTIN checks are syntactic, not a live registration/checksum check.

```json
{
  "schema":"modormc.2b.v1",
  "gstin":"27ABCDE1234F1Z5",
  "period":"2026-10",
  "generated_at":"2026-11-14T10:00:00Z",
  "source_sha256":"replace-with-the-64-character-original-download-sha256",
  "documents":[{
    "supplier_gstin":"29AAAAA0000A1Z5",
    "number":"INV/001","date":"2026-10-01","kind":"INV",
    "amounts":{"taxable":10000,"igst":0,"cgst":900,"sgst":900,"cess":0},
    "itc_available":true,"ims":"accepted","reverse_charge":false,"section":"B2B"
  }]
}
```

Values above are illustrative; the amounts do not imply a rate-selection rule. Supported normal document kinds are INV/CN/DN. CN amounts are negative; other statement kinds are nonnegative. IMS accepts accepted/rejected/pending/deemed_accepted/not_applicable. BOE/ISD identity adapters, cumulative claim tracking and native amendment relationships need dedicated extensions; do not fabricate supplier identities to fit a normal invoice schema. Preserve supplier reference differences for local purchase debit notes and review their reversal treatment explicitly.

For a reviewed CSV mapping, use these exact columns with ISO dates, plain rupee decimals (no thousands separators), and literal true/false booleans:

```text
supplier_gstin,number,date,kind,taxable,igst,cgst,sgst,cess,itc_available,ims,reverse_charge,section
```

```powershell
php scripts/gst-normalize-2b.php mapped.csv 27ABCDE1234F1Z5 2026-10 2026-11-14T10:00:00Z original-download.json statement-v1.json
```

Supply the real original download and its actual generation time. The converter computes its checksum, uses integer money and refuses to overwrite an output. It deliberately leaves complete semantic validation to the authenticated import endpoint. Map all relevant sections, deduplicate effective amendment revisions consciously, and compare totals before upload. Empty template checksums are rejected; an unchanged empty template is not evidence of a nil statement.

## Endpoints and storage

All workspace endpoints are inside the existing authenticated/verified web group, retain CSRF protection for mutations, and use the selected plant rather than a submitted plant/user ID. Base is `/finance/gst-compliance`.

The tenant key is `plant_id`, including when multiple plants share an entity or GSTIN. Workspace responses expose this ID, and the UI includes it on every workspace request as a context assertion. The server still resolves the plant from the authenticated session and checks membership and permissions. An asserted ID different from the selected plant returns HTTP 409 with a reload instruction, preventing a stale tab from operating on another plant after a workspace switch. Invalid IDs return validation errors. Existing callers may omit the assertion and continue to use the authorized selected plant. No additional company scope or schema migration is required for this change.

Plant-scope verification on 2026-10-18: `php vendor/bin/phpunit tests/Feature/GstComplianceTest.php tests/Feature/AccountingPostingSafetyTest.php` passed (32 tests, 152 assertions), using isolated in-memory SQLite. This includes sibling plants sharing an entity/GSTIN, separate books and import identities, denied sibling access, malformed context IDs, and stale-context reads/writes without persisted changes. It does not establish MySQL runtime behavior.

| Method | Suffix | Operation |
|---|---|---|
| GET | `/` | Inertia workspace |
| GET | `/data?period=YYYY-MM` | Books, matching, classification/review history, snapshot status |
| GET | `/audit?period=YYYY-MM` | Verify chain and referenced immutable payloads; return checkpoint |
| GET | `/template?period=YYYY-MM` | Empty normalized JSON template |
| POST | `/imports` | Multipart file plus period; maximum 10 MiB/20,000 documents |
| POST | `/tally-inbound` | Stage one mapped Tally voucher for independent review |
| POST | `/classifications` | source_key, current source_hash, reason, allocation rows and integer-paise amounts |
| POST | `/rules` | version, reason, optional explicit effective-window policy |
| POST | `/snapshots` | Prepare current period |
| POST | `/reviews/{rule|classification|snapshot|inbound}/{id}` | include/exclude, reason, and all checklist flags for snapshot approval; inbound include posts accounting |
| GET | `/snapshots/{id}/export?period=YYYY-MM` | Frozen working-paper JSON/evidence |
| GET | `/tally-export?period=YYYY-MM&company=...` | Balanced posted journal XML |

Mutation bodies contain the reporting `period`. Validation errors return 422; missing permission 403; wrong-scope record 404; changed source, reused version or stale approval 409. Imports are idempotent by exact raw hash. Classifications are append-only revisions; an identical latest payload returns its prior record. Rule version labels cannot be reused with changed policies.

New tables are `mm_gst_periods` (scope, revision and chain head), `mm_gst_imports`, `mm_gst_rules`, `mm_gst_classifications`, `mm_gst_reviews`, `mm_gst_snapshots`, `mm_gst_inbound`, `mm_gst_events`. All except the scope/head row are protected against updates/deletes by database triggers. Source documents and legacy journals are not subject to these new triggers. Audit verification is explicit to avoid doing a full historical scan on each screen refresh; retain its returned head/sequence independently. Database administrators can remove triggers or rewrite data, so external retained checkpoints/backups remain necessary to detect a complete rewrite.

## Tally journal exchange example

Download XML from the worksheet screen using the exact loaded Tally company name. Set a stable `GST_TALLY_NAMESPACE` for the installation and preserve it across domain changes/restores. Configure any exact ledger-name overrides in `config/gst_compliance.php`; otherwise the web ledger title is used. Existing Tally masters must match. The export refuses nonbalanced/invalid headers or lines and cross-plant/deleted ledgers, and reads posted journals only. It does not create accounting or GST facts.

Enable the local TallyPrime XML listener, commonly `http://127.0.0.1:9000`, and load a backed-up test company. With PHP/PDO SQLite and this repository available on the desktop:

```powershell
php scripts/tally-sync.php send tally-journals.xml C:\protected\tally-state.sqlite
```

The example splits the frozen export into single-voucher requests. It durably reserves each GUID/hash before contacting Tally, skips already confirmed identical deliveries, and refuses a changed payload under the same GUID. HTTP success is insufficient: CREATED=1, ERRORS=0 and a positive LASTVCHID are required. A timeout/crash leaves delivery uncertain and never blindly resends. Preserve the local state database/receipts and do not reuse it for a different source installation or Tally company.

Inspect the target voucher GUID/content and import logs. Quiesce old workers before retrying a verified-absent voucher. Resolve with evidence:

```powershell
php scripts/tally-sync.php resolve THE-GUID C:\protected\tally-state.sqlite confirmed "Verified matching voucher and amount in the exact target company"
php scripts/tally-sync.php resolve THE-GUID C:\protected\tally-state.sqlite retry_absent "Old workers stopped and GUID verified absent in target company export"
```

Only use the resolution matching your evidence. A changed posted journal needs explicit reversal/correction in both books; the sample will not silently overwrite Tally. Generic Journal vouchers omit inventory, party bill allocations and invoice-specific statutory GST tags. Build/validate specialised voucher adapters using known-good XML exported from your exact Tally release/company. The local example is optional outbound exchange, not a deployed background service or complete master/voucher crawler.

### Inbound Tally staging

Export vouchers from the exact Tally release/company and explicitly map its ledger names to active-plant web ledger IDs. No native Tally XML crawler or automatic master creation is assumed. Prepare one mapped JSON voucher, upload it in the worksheet's Inbound Tally section, and have a different user with `TALLY_POST` review it. Example (integer paise):

```json
{
  "remote_guid":"the-original-tally-voucher-guid",
  "date":"2026-10-17",
  "narration":"Mapped supplier payment",
  "reason":"Original export retained; debit and credit ledger mappings verified",
  "lines":[
    {"account_id":1,"debit":11800,"credit":0},
    {"account_id":2,"debit":0,"credit":11800}
  ]
}
```

The API also accepts this object plus `period`. Staging validates balance and money types but does not create a journal. Identical retries reuse the immutable stage record. Corrections before posting create a new mapping revision with the same remote GUID; older revisions cannot be approved. A changed voucher after posting conflicts and requires explicit reversal/correction. Approval creates one balanced `tally_import` journal and review/audit event in the same transaction, after checking every ledger belongs to this plant. Posting rights are separate from tax-review rights, and the preparer cannot approve their own stage.

Accepted inbound journals are excluded from outbound exports. Outbound web GUIDs are rejected when staged back into this plant, preventing echoes. Raw staging history remains immutable. Accepted journals do not fabricate invoices or GST eligibility: create supported manual tax classifications separately where applicable. Approval cannot be undone by an exclusion decision; use the established correction/reversal process. The legacy journal module remains editable, so compare both systems when correcting imported data.

## Official references checked

The implementation uses the official sources checked on 17 October 2026 in the preceding research. This is bounded verification, not an exhaustive certification of every applicable notification or business condition.

- [CBIC Circular 170/02/2022-GST](https://cbic-gst.gov.in/pdf/Circular-170-02-2022-GST.pdf): ITC/reversal/disclosure distinctions and net-credit structure.
- [GSTN GSTR-3B FAQ](https://tutorial.gst.gov.in/userguide/returns/GSTR3B.htm): table structure, ECO/POS disclosure and October-2025 advances change.
- [GSTN October-2025 IMS FAQ](https://tutorial.gst.gov.in/downloads/news/creative_faq_on_gstr9_for_24_25_dt_15_oct_25_v6_final.pdf): pending/credit-note review and partial reversal considerations.
- [GST Council Secretariat January 2026 update](https://gstcouncil.gov.in/sites/default/files/2026-02/newsletter_january_issue.pdf): cash-ledger-sensitive interest and utilisation changes.
- [GST Council Secretariat March 2026 update](https://gstcouncil.gov.in/sites/default/files/2026-04/newsletter-march-2026.pdf): liability-breakup confirmation process.
- [Tally XML integration](https://help.tallysolutions.com/xml-integration/), [sample XML](https://help.tallysolutions.com/sample-xml/), [debit/credit conventions](https://help.tallysolutions.com/tdl-faq/): documented exchange boundaries. No live Tally company was accessed.

## Validation and remaining boundaries

The focused test files pass **29 tests / 126 assertions** for the new workspace, posting guards and Tally export/inbound handling. They assert that SQLite is in memory and do not migrate the configured business database. The separate Vite production build passed, and the final Vue script/template also compiled after adding explicit supplier-identity fields. Existing published assets were not replaced. Twelve authenticated workspace routes are registered.

```powershell
php artisan test tests/Feature/GstComplianceTest.php tests/Feature/AccountingPostingSafetyTest.php --compact
php artisan test tests/Feature/PurchaseOrderControllerTest.php --compact
```

The initial broader regression check passed the 19 purchase-order workflow tests and 10 selected invoice/bill/note regression tests. It also found nine outdated opening-balance tests whose fixtures omit `mm_opening_balance_audit_logs` or reference removed import routes, and two register-sort/bill-recalculation expectation failures. The latter two were reproduced with the original Git versions of both posting classes preloaded, confirming they predate these posting changes. They are not reported as successful tests or silently weakened here.

Live MySQL trigger execution, browser interaction against a migrated authenticated workspace, real taxpayer exports, live Tally import and production deployment remain acceptance steps. Cross-period automatic ITC entitlement/claim ledgers, registration-wide/quarterly consolidation, native portal/Tally download crawlers, specialised voucher adapters and complete immutable accounting across every old writer are explicit further work, not hidden guarantees of this module.
