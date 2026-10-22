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

Customer and site discovery is shared within the selected plant. Commercial forms accept those plant-local reference IDs. Customer/site master writes are not exposed by these routes. Lead approval also requires a visible owned lead; nested PO orders and production relations are filtered by plant and visible order ownership. Plant-shared catalogs (mix designs, taxes, vehicles and drivers) remain available as form inputs; product units are global reference data.

## Admin lead review and sales lead ownership

Main app administrative lead routes (main-app token and X-Plant-Id required):

- GET `/api/mobile/leads` and `/api/mobile/leads/{lead}`
- GET `/api/mobile/leads/statuses` and `/api/mobile/leads/{lead}/statuses`
- PUT/PATCH `/api/mobile/leads/{lead}`; qualify with `{"status":"Qualified"}`
- POST `/api/mobile/leads/{lead}/approve`; no body required

These require an administrator role in the selected plant (or system admin) and
the corresponding CRM_LEAD VIEW, UPDATE or APPROVE permission. They can review
other executives’ leads in that plant. Qualification/approval preserve creator
and assignment. Unrelated plants remain inaccessible. Admin lead creation,
deletion, conversion and follow-up routes are not added by this review feature.

Sales `/api/mobile/v1/leads` and its lead actions use `created_by` equal to the
logged-in user, independent of assignment. Reassignment does not expose someone
else’s lead or remove the creator’s access. This is distinct from admin review.

## Quotation submission and admin approval

Sales app quotations are scoped by `created_by` to the logged-in executive.
POST `/api/mobile/v1/quotations/{quotation}/submit` changes the existing record to
status 1 (`pending`) and records the submitting user/time. Draft quotations do not
appear in the admin review queue.

Admin main-app token and X-Plant-Id are required:

- GET `/api/mobile/quotations?approval_state=pending` lists submitted quotations in the selected plant.
- GET `/api/mobile/quotations/{quotation}` displays the submitted quotation, customer/site and priced items.
- POST `/api/mobile/quotations/{quotation}/review` accepts `{"decision":"approved","comment":"Rates approved"}` or `{"decision":"rejected","comment":"Revise rates"}`.

The review requires an administrator role and QUOTATION.VIEW/APPROVE permissions.
Only pending quotations can be reviewed; self-review and repeated decisions are
rejected. Rejection requires a comment. Sales-app review requests return 403.
Admin reads include submitted, approved and rejected records in the selected
plant and do not broaden other commercial record endpoints.

After review, GET `/api/mobile/v1/quotations/{quotation}` and its list return the
same record with status 2 / `approved` or status 3 / `rejected`, plus
`mobile_approval_comment`, `mobile_reviewed_by`, and `mobile_reviewed_at`.
Creator and sales executive ownership remain unchanged. Approved records cannot
be edited; editing a rejected record resets it to draft for resubmission.
Clients should refresh their list/detail after a review or on returning to the
screen; this change does not introduce a new realtime subscription.

## Import and test with Postman / Apidog

Import `docs/postman/sales-executive-mobile.postman_collection.json`,
`docs/postman/mobile-auth.postman_collection.json`, and the shared
`docs/postman/sales-executive-mobile.postman_environment.json`. Select that same
environment for both collections. Set `base_url`, sales `email`/`password`,
`admin_email`/`admin_password`, and an accessible `plant_id`. Use lookups from
that plant to set customer/site/mix-design/tax IDs.

1. Sales Authentication > Login captures `data.token` as `api_token`.
2. Create a sales lead or quotation; creation captures `lead_id`/`quotation_id`.
3. Admin app authentication > Admin login captures top-level `token` as `admin_token`.
4. Admin lead review > Qualify executive lead / Approve executive lead.
5. Sales Quotations > Submit for approval.
6. Admin quotation review > Pending queue, detail, then approve **or** reject.
7. Sales Quotations > Refresh quotation after admin review to see the status/comment.

Run alternative OTP, rejection, deletion and negative tests individually. These
collections contain independent examples, not a single sequential Runner flow.
The main-app and sales tokens must not be interchanged. Collection variables allow
standalone imports, while the shared environment carries captured IDs between
collections. Refresh imports after regenerating. Apply workflow updates directly
with `node tools/update-mobile-review-postman.cjs`; the base sales generator also
runs this updater automatically.

## Shared customer and site reference data

Customers and sites are available to every authorized user of the selected
plant through GET list/detail and lookup requests. They are shared independently
of creator, sales executive assignment and commercial history. Foreign-plant and
soft-deleted IDs remain excluded. The same plant-local IDs are accepted when
creating/editing quotations, customer POs and orders. Referencing an existing
customer/site does not grant permission to edit or delete that master record.
Sales mobile customer/site routes remain GET-only; commercial ownership and
admin approval rules are unchanged. Customer progress still counts only orders
visible to the current user.
