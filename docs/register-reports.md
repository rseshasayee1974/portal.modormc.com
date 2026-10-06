# Sales and purchase registers

Open **Report > Sales Register** or **Report > Purchase Register**. Both also appear in the Accounting & Finance report catalog, with their existing Production/Inventory entries retained.

Excel exports use **Invoice No** as the only document-number column in Sales Register and **Bill No** in Purchase Register. This applies to Standard (Summary and Detailed) and Address & GST formats, including scheduled and legacy Excel exports.

**Report > Detailed Sales Register** opens the item layout based on `Sales_Register20260923 (1).xlsx`. It adds payment type, product rate, net amount, tax name, taxable sales, TCS, unloading site, truck, e-invoice status, cancellation/acknowledgement dates and creator. Party Type and Description are omitted. The last columns are **Net Amount > Unloading Point > IRN > E-Invoice Status > ACK Date > Cancel At > Created By**.

All sales/purchase summary and detail layouts show paired CGST/SGST columns at 2.5%, 6%, 9% and 14% (GST 5%, 12%, 18% and 28%), followed by IGST 5%, 12%, 18% and 28%. These twelve columns remain visible even when zero; every additional recorded rate is also included. The separate CGST Total, SGST Total, UTGST Total and IGST Total columns are omitted from the screen, Excel and PDF. Rate-column footer totals and Total Tax remain available.

Gross uses the stored item total including tax; **Sales GST (Taxable)** uses the item's taxable subtotal. This avoids repeating a complete invoice amount for every product on a multi-item invoice. Tax names describe recorded tax splits. Dispatch payment mode, unloading and truck come only from matching, non-deleted dispatches within the invoice's plant; unrelated invoice references are not treated as dispatch IDs. Legacy dispatch customers also work with the customer filter. Creator uses `users.email`, falling back to `users.username`.

Choose a date range, customer/supplier, GST type, active/all/cancelled documents, and Summary or Detailed view. Sales also supports payment status. Summary groups matching items by invoice/bill. Detailed view includes product, HSN/SAC, quantity, unit, rate and creator; sales includes available IRN, acknowledgement and cancellation information.

Tax columns come from the complete filtered period, and footer totals cover all pages. Amounts use stored item values; document-level shipping, discounts, adjustments and rounding are excluded. This is stated on the screen and in exports. Purchase dates use billed date, then order date, then creation date. Sales tax splits use stored invoice-item tax lines. Purchases use the item's explicit tax group, otherwise the existing GSTIN-state comparison convention. An odd paisa is retained in the second component so the split equals the stored tax amount.

Excel and PDF exports include every matching record and preserve the selected view and filters. Both show the same rate-wise GST columns. All register PDFs use A4 landscape, including scheduled exports, shared downloads and legacy export entry points. PDF puts long audit fields below each detailed row. When more than six tax-rate columns are present, PDF prints the complete rate breakdown in continuation tables with matching row numbers and document references. Scheduled exports, shared PDFs and legacy export entry points use the same column definitions.

### Standard Sales Register Excel addresses

With **Excel Format > Standard register**, both Summary and Detailed sales exports include Address_1, Address_2, City, Zipcode, Shipping Address_1, Shipping Address_2, Shipping Zipcode and Truck. Billing details follow the primary contact/linked party address selection below; shipping lines and ZIP code come from the dispatch unloading site. Missing values remain blank. ZIP codes stay as text, long addresses wrap, and Date/Party remain frozen while scrolling. Existing invoice grouping, item rows, GST rate splits and totals are preserved. These address columns also apply to scheduled and legacy Sales Register Excel exports; screen/PDF layouts and the standard Purchase Register are unchanged.

The standard sales Excel order is Date, Customer, billing/shipping addresses, GSTIN, Type, Invoice No, product/delivery details, discounts and taxable value, GST rate splits, then **Total Tax > Round Off > Net Amount**. Detailed exports place **Truck immediately after Product** and append **Unloading Point > IRN > E-Invoice Status > ACK Date > Cancel At > Created By** after Net Amount. Summary exports retain one row per invoice, with Truck after Invoice No. Party Type, Description, TCS component and TCS rate columns are omitted from this format; Total Tax retains every recorded tax amount. Sales continues to export only Invoice No.

### Address & GST Excel format

Choose **Excel Format > Address & GST (item wise)**, then **Export Excel** in either register. This additional format always exports one row per matching item, even when the screen is showing the summary. Sales requires the Detailed Sales Register View and Export permissions. Standard Excel and PDF layouts remain available.

The 26 columns match the supplied layout: DATE, PARTY, ADDRESS_1, ADDRESS_2, CITY, STATE, ZIPCODE, SHIPPING ADDRESS, SHIPPING ZIPCODE, TYPE, INVOICE NO, TRUCK, GSTIN, PRODUCT, HSN/SAC, QUANTITY, UNIT, RATE, GROSS, SALES GST, TAX NAME, TAX AMOUNT, CGST, SGST, IGST and ROUNDOFF. Dates display as `dd-mm-yyyy`, tax amounts retain two decimals, and ZIP codes, HSN/SAC and document identifiers remain text.

Party addresses use the primary contact's billing/primary address, falling back to the party's linked address. Sales shipping details come from the dispatch's unloading site. Purchases use the receiving plant's address and the distinct trucks recorded against that purchase item's active inwards. Missing values remain blank; purchase TYPE is blank because purchase orders do not store Cash/Credit. All related lookups stay within the selected plant and exclude deleted records.

GROSS is the stored item total including tax. The sample's SALES GST heading means the taxable item value for both sales and purchases. ROUNDOFF is the stored document round-off, shown only on the first matching item of each invoice/bill so totals do not duplicate it. Other document-level adjustments and charges are not allocated to item values. Additional tax components remain included in TAX AMOUNT; the fixed sample layout has only CGST, SGST and IGST component columns.

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
php -d memory_limit=512M tests/Standalone/register-address-gst.php
php tests/Standalone/report-export-queue.php
```

The register test uses an isolated in-memory SQLite database. It covers date/party/tax/status filters, pagination, plant boundaries, multi-item summary grouping across query chunks, numeric tax totals, typed Excel dates/identifiers, text formula safety, complete exports, and the idempotent menu migration. Temporary Excel/PDF files are written to the printed system-temp directory for layout review.

On 23 September 2026, the local database became available: both menu migrations were applied and the detailed sales query was verified against its records. Standalone checks also cover dispatch/customer mapping, foreign-plant and deleted-reference exclusion, TCS, zero-rate columns, sample Excel/PDF fields, and both menu migrations. The PDF was rendered and visually checked. An authenticated browser walkthrough remains separate from these service/export checks.
