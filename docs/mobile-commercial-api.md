<!--
Author: Cipher
Created: 2026-10-21 17:53:48 Asia/Calcutta (UTC+05:30)
-->

# Mobile commercial API and route gaps

The main mobile app uses `/api/mobile`; the separate sales app uses
`/api/mobile/v1`. Both load `routes/mobile-commercial.php`. Existing sales
URLs and route names remain available. All endpoints return JSON.

Send `Authorization: Bearer <token>`, `Accept: application/json`, and
`X-Plant-Id: <id>`. Main app endpoints accept the wildcard Sanctum token issued
by its existing login/OTP workflow. Sales endpoints retain their sales device
validation middleware. Plant membership and active account checks apply to both.
Commercial records retain the existing personal ownership restrictions for every
role. A personnel assignment is required for creating commercial records.

## Shared endpoints (relative to either app prefix)

| Method | Path | Purpose |
| --- | --- | --- |
| GET, POST | `/quotations` | Paginated listing, create |
| GET, PUT/PATCH, DELETE | `/quotations/{quotation}` | Detail, update, delete |
| POST | `/quotations/{quotation}/submit` | Submit for approval |
| POST | `/quotations/{quotation}/review` | Approve/reject |
| GET, POST | `/orders` | Paginated listing, create |
| GET, PUT/PATCH, DELETE | `/orders/{order}` | Detail with progress, update, delete |
| GET | `/orders/{order}/batches` | Paginated batches |
| GET | `/orders/{order}/dispatches` | Paginated dispatches |
| GET, POST | `/customer-pos` | Paginated listing, create |
| GET, PUT/PATCH, DELETE | `/customer-pos/{customer_po}` | Detail, update, delete |
| POST | `/customer-pos/{customer_po}/convert-salesorder` | Allocate PO quantities to sales orders |
| GET, POST | `/batch-schedules` | Paginated listing, create |
| GET, PUT, DELETE | `/batch-schedules/{schedule}` | Detail, update, delete |

Main app lookup endpoints added: GET `/lookups`, `/customers`, `/sites`,
`/mix-designs`, `/taxes`. These reuse the sales app lookup responses.

## Customer PO payloads

Create/update requires `patron_id`, `site_id`, `order_date` (YYYY-MM-DD),
`is_tax_inclusive`, and `items`. Each item requires `mix_design_id`, `quantity`,
`rate`; optional fields are `tax_id`, `concrete_pump`, `pump_rate`.
Optional header fields: `customer_po_reference`, `notes`, `quotation_id`.
Reference and ownership are assigned by the server. Totals are calculated by the
shared quotation calculator, including child taxes.

For quotation conversion, pass an approved accessible `quotation_id`; items are
copied from that quotation and the customer/site must match. A quotation may have
one active PO. Updates cannot switch the linked quotation. Linked quotation items
remain authoritative. Completed POs and POs with orders/dispatches cannot be
edited or deleted.

Sales order conversion payload:

```json
{"items":[{"item_id":123,"quantity":5}]}
```

Item IDs must belong to the PO. Quantities must be positive and cannot exceed
remaining quantity for the mix design, counting non-cancelled sales orders.
Conversion runs in a transaction with plant/PO locks and updates PO completion.
Existing direct order creation behavior is unchanged; it has its own validation.

## Remaining routes missing relative to the web workflows

These are not implemented by this change and must have mobile-specific scope and
response handling before exposing web controller actions:

| Web function | Missing mobile endpoint candidate |
| --- | --- |
| Quotation PDF download | GET `/quotations/{quotation}/download` |
| Quotation report | GET `/quotations/{quotation}/report` |
| Send quotation email | POST `/quotations/{quotation}/send-email` |
| Web quotation conversion toggle | PATCH `/quotations/{quotation}/convert` (PO creation now provides the actual conversion) |
| Schedule status change | PATCH `/batch-schedules/{schedule}/status` |
| Schedule dispatch creation | POST `/batch-schedules/{schedule}/create-dispatch` |
| Schedule board/aggregates | GET `/batch-schedules/data` |
| Schedule dedicated dropdowns | GET `/batch-schedules/dropdowns` (shared `/lookups` supplies basic inputs) |
| Direct PO dispatch creation | POST `/customer-pos/{customer_po}/dispatches` |

The sales app also has CRM lead CRUD/conversion, follow-ups, attendance,
notifications, marketing summary, customer/site detail, plants, me and logout.
Those app-specific routes remain under `/api/mobile/v1` and were not copied into
the main app. The main app already has its own dashboard, auth and reports.
Its missing equivalents include GET `/customers/{customer}`, `/sites/{site}`,
`/plants`, and POST `/auth/logout`.

Sales OpenAPI/Postman artifacts include customer PO CRUD and conversion requests,
with customer_po_item_id capture and order_id capture after conversion.

## Verification

Artisan route listing and PHP syntax checks passed. Added regression tests for
app-token separation, plant denial, PO ownership, validation and calculated PO
updates/deletion. PHPUnit could not execute: PHP reports `is_readable` false for
the existing `vendor/autoload.php`, so PHPUnit rejects its bootstrap before tests
start, although Artisan can require that file successfully.
