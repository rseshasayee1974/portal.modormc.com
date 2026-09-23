# Sales and purchase registers

Open **Report > Sales Register** or **Report > Purchase Register**. Both also appear in the Accounting & Finance report catalog, with their existing Production/Inventory entries retained.

**Report > Detailed Sales Register** opens the item layout based on `Sales_Register20260923 (1).xlsx`. It adds payment type, product rate, gross, tax name, taxable sales, TCS, unloading site, truck, description, e-invoice status, cancellation/acknowledgement dates, party type and creator. The two `TYPE` headings in the sample are named **Payment Type** and **Party Type**.

All sales/purchase summary and detail layouts show paired CGST/SGST columns at 2.5%, 6%, 9% and 14% (GST 5%, 12%, 18% and 28%), followed by IGST 5%, 12%, 18% and 28%. These twelve columns remain visible even when zero; every additional recorded rate is also included. The separate CGST Total, SGST Total, UTGST Total and IGST Total columns are omitted from the screen, Excel and PDF. Rate-column footer totals and Total Tax remain available.

Gross uses the stored item total including tax; **Sales GST (Taxable)** uses the item's taxable subtotal. This avoids repeating a complete invoice amount for every product on a multi-item invoice. Tax names describe recorded tax splits. Dispatch payment mode, unloading and truck come only from matching, non-deleted dispatches within the invoice's plant; unrelated invoice references are not treated as dispatch IDs. Legacy dispatch customers also work with the customer filter. Creator uses `users.email`, falling back to `users.username`.

Choose a date range, customer/supplier, GST type, active/all/cancelled documents, and Summary or Detailed view. Sales also supports payment status. Summary groups matching items by invoice/bill. Detailed view includes product, HSN/SAC, quantity, unit, rate and creator; sales includes available IRN, acknowledgement and cancellation information.

Tax columns come from the complete filtered period, and footer totals cover all pages. Amounts use stored item values; document-level shipping, discounts, adjustments and rounding are excluded. This is stated on the screen and in exports. Purchase dates use billed date, then order date, then creation date. Sales tax splits use stored invoice-item tax lines. Purchases use the item's explicit tax group, otherwise the existing GSTIN-state comparison convention. An odd paisa is retained in the second component so the split equals the stored tax amount.

Excel and PDF exports include every matching record and preserve the selected view and filters. Both show the same rate-wise GST columns. All register PDFs use A4 landscape, including scheduled exports, shared downloads and legacy export entry points. PDF puts long audit fields below each detailed row. When more than six tax-rate columns are present, PDF prints the complete rate breakdown in continuation tables with matching row numbers and document references. Scheduled exports, shared PDFs and legacy export entry points use the same column definitions.

## Deployment

Apply the additive menu migration to the initialized application database:

```powershell
php artisan migrate --path=database/migrations/2026_09_23_120000_add_register_report_menus.php --force
php artisan migrate --path=database/migrations/2026_09_23_130000_add_detailed_sales_register_menu.php --force
npm.cmd run build
```

The menu entries use individual report View permissions; see [report-permissions.md](report-permissions.md) for assignment and the permission migration. The endpoints require an active plant and reject requests for another plant. No accounting data or report tables are migrated.

## Verification

```powershell
php tests/Standalone/register-reports.php
php tests/Standalone/report-export-queue.php
```

The register test uses an isolated in-memory SQLite database. It covers date/party/tax/status filters, pagination, plant boundaries, multi-item summary grouping across query chunks, numeric tax totals, typed Excel dates/identifiers, text formula safety, complete exports, and the idempotent menu migration. Temporary Excel/PDF files are written to the printed system-temp directory for layout review.

On 23 September 2026, the local database became available: both menu migrations were applied and the detailed sales query was verified against its records. Standalone checks also cover dispatch/customer mapping, foreign-plant and deleted-reference exclusion, TCS, zero-rate columns, sample Excel/PDF fields, and both menu migrations. The PDF was rendered and visually checked. An authenticated browser walkthrough remains separate from these service/export checks.
