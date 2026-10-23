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
Sales app commercial records retain personal ownership restrictions for every
role. In the main app, administrators can manage customer POs and sales orders
throughout the selected plant, including records assigned to other executives.
Existing ownership is preserved; administrators without personnel can create
unassigned records. Other roles retain personal ownership restrictions.

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

## Sales order CRUD quick test

Use the sales token plus X-Plant-Id with `/api/mobile/v1/orders`:
GET list, POST create, GET `/{order}` detail/progress, PUT/PATCH `/{order}` update,
and DELETE `/{order}` soft delete. PUT and PATCH accept the full form.

```json
{"customer_id":1,"site_id":1,"mix_design_id":1,"customer_po_id":null,"total_qty":10,"rate":4500,"is_tax_inclusive":false}
```

Replace reference IDs with IDs from the selected plant’s lookups. Direct orders
require a rate. An accessible customer PO is optional; when selected, its matching
mix-design line supplies pricing and customer/site must match. Creation assigns
the current plant/personnel and generates the order number automatically. A newly
created order appears in its owner’s list. Administrators can also access
unassigned orders in the main app. Production/schedule/dispatch activity or a completed/cancelled
state prevents update/delete. Postman includes direct-order POST/PUT/PATCH examples,
PO-based examples, detail, batches/dispatches and a separate delete action.

## Main-app administrator order reads

GET `/api/mobile/orders` and order detail/batches/dispatches allow authorized
administrators to read all non-deleted orders in X-Plant-Id, including unassigned
orders, without requiring a personnel profile or module permissions. The verified
main-app context is set by MobileAppPlantContext; supplying a query/body field
cannot enable it. Sales `/api/mobile/v1/orders` retains executive ownership.
Admin writes use the same token authentication and plant context.

## Admin customer PO and sales order access

The existing `/api/mobile/customer-pos` and `/api/mobile/orders` routes provide
full CRUD; PUT/PATCH expects the complete editable payload. Customer PO and order
CRUD, PO conversion, and batch/dispatch history do not require module permissions.
Access uses the login token and verified plant context; administrator visibility
and sales-app ownership restrictions still apply.

Admin PO creation can use an approved quotation from any executive in the
selected plant. PO conversion is transactional and rejects duplicate item IDs,
foreign items, nonpositive quantities, completed POs, and over-allocation.
Admin order writes linked to a PO also enforce its remaining mix quantity.
Orders with production, dispatch, or schedule activity and completed/cancelled
orders reject edits and deletion. Foreign-plant or deleted IDs return 404;
invalid payloads and business-rule failures return 422. Tokens and plant context
remain mandatory.

`SalesMobileApiTest::test_admin_commercial_crud_conversion_and_failure_boundaries`
covers admin CRUD without personnel, tax totals, conversion, rollback on excess
quantity, immutable records, validation, plant isolation, access without module permissions, and missing-token rejection.

## Postman admin CRUD workflow

Import `docs/postman/mobile-auth.postman_collection.json` and
`docs/postman/sales-executive-mobile.postman_environment.json`. The sales collection
also includes the same admin folders. Select the environment and set `base_url`,
`admin_email`, `admin_password`, and `plant_id`. Run **Admin login (main app)**;
it saves `admin_token`. Run **Admin commercial lookups**, then set `customer_id`,
`site_id`, and `mix_design_id` from the selected plant responses.

Run **Admin customer PO CRUD**, **Admin sales order CRUD**, and **Admin PO conversion
and negative cases** in that order. These folders need no module permissions.
Created IDs use separate `admin_*` variables, preserving sales workflow IDs.
The requests include deletes on disposable records. Conversion allocates 4 of
10 m3; negative cases assert 422 for invalid quantities, duplicate lines,
over-allocation and allocated PO changes, 404 after deletion, and 401 without
a login token. Rerun PO CRUD to create a fresh PO before repeating conversion.
Lead and quotation review folders retain their separate authorization rules.

Regenerate these admin requests with `node tools/update-mobile-review-postman.cjs`.

## Customer PO errors

Customer PO requests return field-level `errors` with HTTP 422 for invalid
input and a clear 422 message when a PO is completed or allocated. Missing or
inaccessible POs return HTTP 404 with `Customer PO not found or unavailable in
the selected plant.` Authentication and plant checks retain their existing
HTTP status codes.

Unexpected PO failures return HTTP 500 with an operation-specific `message`
and an `error_reference` UUID. For example:

```json
{
  "message": "Unable to update the customer PO. Please try again or contact support with the error reference.",
  "error_reference": "b178cd62-ab43-4a46-af94-86b580fa7d09"
}
```

Use the reference to locate `Customer PO API request failed.` in the application
log. Exception details remain in the log. Failed saves roll back header, item,
and quotation changes. Dispatch guards follow `sales_order_id` through sales
orders; they do not require a `customer_po_id` column on `mm_dispatches`.

Sales order detail, update, deletion, batch history, and dispatch history return
HTTP 404 with `Sales order not found or unavailable in the selected plant.` for
missing, deleted, foreign-plant, or ownership-restricted orders. Responses do not
expose the internal model class or distinguish inaccessible records from missing
records. This applies to both app prefixes.

## Batch schedule form options

Use `GET /api/mobile/batch-schedules/options` in the admin app or
`GET /api/mobile/v1/batch-schedules/options` in the sales app. Send the matching
login token and `X-Plant-Id`. No module permissions are required.

```json
{
  "success": true,
  "data": {
    "vehicles": [{"id": 12, "value": 12, "label": "TN-01-AB-1234", "registration": "TN-01-AB-1234"}],
    "drivers": [{"id": 45, "value": 45, "label": "Driver Name"}]
  }
}
```

Load once when opening the create/edit form. Submit vehicle `value` as
`vehicle_id` and driver `value` as `driver_id`. Filters match the web schedule
form: active Transit Mixer/Truck vehicles and active personnel with Driver or
Operator in their designation, restricted to the selected plant. Deleted
records are excluded; empty lists return 200 with empty arrays. These are
eligible options, not time-slot availability; saving checks assignment conflicts.
Reload after changing plants.

The existing `GET /lookups` also provides `vehicles` and `drivers` with other
commercial data. Reuse it if already loaded; otherwise the smaller options
endpoint avoids unrelated data and two separate network requests. The schedule
listing continues to return schedules only. Postman collections include
**Batch schedules > Driver and truck options**; MODORMC keeps it at the root.

## Lookup plant context

Admin `GET /api/mobile/dropdowns` and sales `GET /api/mobile/v1/lookups` return
reference options for the authenticated user's verified `X-Plant-Id`. The selected
plant must be in that user's active plant memberships. Missing plant headers
return 422; inaccessible or revoked plant memberships return 403; missing tokens
return 401. A query/body `plant_id` cannot override the header.

Sales taxes and their children, vehicles, pumps, and drivers are restricted to
that selected plant. Dropdown helpers receive the verified plant explicitly;
lookup cache arguments include that plant. Switching plants reloads those lists.
These reference options are shared within the accessible plant, rather than
restricted to records created by the user. Statuses, sources, priorities, activity
types, and pump types are static application option lists. Commented-out lookup
sections remain excluded from this response.
