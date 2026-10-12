<!--
Author: ragul-onemodo
Created: 2026-10-09 18:13:46 Asia/Calcutta (UTC+05:30)
-->

# Sales Executive Mobile API v1

These Laravel JSON APIs support the sales executive mobile screens: dashboard, leads, marketing enquiries and visits, follow-ups, customers, quotations, sales orders, production progress and batch schedules. They are separate from the existing mobile dashboard routes and web/Inertia pages. The published Sites screen demonstration does not call these APIs yet.

## Deployment

Use the ERP server as the API host. The Sites preview address is not an API host. The base path is `/api/mobile/v1`; for example `https://your-erp.example/api/mobile/v1`.

Run on the Laravel application server:

```sh
php artisan migrate --force
php artisan permission:cache-reset
php artisan optimize:clear
php artisan route:list --path=api/mobile/v1
```

The new migration `2026_10_09_180500_add_sales_mobile_quotation_approval.php` adds six approval audit fields to `mm_quotations` and five permissions. Existing CRM, schedule, batch and Sanctum migrations must already be installed. Existing quotations start with mobile approval state `draft`; an already accepted or converted ERP quotation remains protected by its existing status and PO links. No existing accounting data is rewritten. Rollback removes the six fields, including their approval history; it retains permission grants because they might have existed before this migration.

Configure a reliable shared cache with atomic lock support for OTP challenges, single-use verification and rate limiting. WhatsApp OTP reuses the ERP's Tendigit integration. Set `TENDIGIT_LICENSE_NUMBER` and `TENDIGIT_API_KEY`, with approved login template `MOBILE_WHATSAPP_OTP_TEMPLATE` (default `modormcloginotp`). Its three positional parameters must be email, `Your Code`, and the six-digit OTP, matching the existing ERP login template. `MOBILE_OTP_COUNTRY_CODE` defaults to `91` for ten-digit local numbers; international numbers must include the country code. The user must have a uniquely registered `mm_users.mobile` number with WhatsApp enabled. A provider rejection or missing credentials returns 503 without creating a token. HTTP 202 confirms provider submission, not final WhatsApp delivery. Live delivery requires valid provider credentials and an approved template and was not exercised by the automated tests.

Deploy over HTTPS. Device tokens expire after 30 days, or sooner if Sanctum's global expiration setting requires it. Keep tokens in the mobile platform's secure storage. Reauthenticate after expiry; this API has no refresh-token endpoint. Administrators can revoke a lost device's Sanctum personal access token.

The development machine's MySQL connection was unavailable during implementation, so this migration still needs to be applied to that database. Automated tests use an isolated in-memory SQLite database and do not write production records.

## Postman setup

Import both files:

- `docs/postman/sales-executive-mobile.postman_collection.json`
- `docs/postman/sales-executive-mobile.postman_environment.json`

Select **MODO Sales Mobile - Local** and set `base_url` to the ERP origin without a trailing slash and `plant_id`. Choose one authentication path:

- **Email/password:** set `email` and `password`, then run **Login**. Its 200 response stores the bearer token in `api_token` directly.
- **Mobile/WhatsApp OTP:** set `mobile`, then run **Send WhatsApp OTP**. Its 202 response stores `challenge_id` and clears the previous token/OTP. Enter the received WhatsApp code in `otp` and run **Verify OTP** to store the bearer token. No email or password is required for this path.

After either path, run **Accessible plants** and **Dropdown lookups**.

Copy actual IDs from dropdown responses into `customer_id`, `customer_po_id`, `site_id`, `mix_design_id` and `sales_tax_id`. Create requests save their returned lead, activity, quotation, order or schedule ID automatically. The collection uses `today` and `validity_date` variables; clear or update them on a different testing day. All example people and values are demonstration data.

For approvals, use a different reviewer. Set `reviewer_email` and `reviewer_password` and run **Reviewer login**, or set `reviewer_mobile`, run **Reviewer send WhatsApp OTP**, enter the code in `otp` and run **Reviewer verify OTP**. Review requests use `reviewer_token`. Complete executive verification before starting reviewer OTP because both use the `challenge_id` and `otp` variables. The reviewer requires `QUOTATION.APPROVE` and access to the quotation. Approval and rejection are alternatives; do not run both on the same submitted quotation. Likewise, do not run the whole collection blindly: the delete folder soft-deletes records, converted leads cannot be deleted, and logout must run last. Run destructive actions individually against disposable records.

The collection and environment contain no real credentials. Exporting a populated environment can include tokens/passwords, so remove those values before sharing it. To regenerate the collection after intentional API changes, run `node tools/build-sales-mobile-postman.cjs`.

## Authentication and headers

Public requests:

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/login` | Email/password login; issues a device token directly |
| POST | `/auth/send-otp` | Mobile-number login; sends WhatsApp OTP without a password |
| POST | `/auth/verify-otp` | Verifies the mobile OTP challenge and issues a device token |

Login JSON:

```json
{"email":"executive@example.com","password":"your-password","device_name":"Android - Sales phone"}
```

Successful password login or mobile OTP verification is HTTP 200:

```json
{"success":true,"data":{"token":"device-bearer-token","token_type":"Bearer","expires_at":"2026-11-08T10:00:00+05:30","user":{"id":12,"name":"Sales Executive","email":"executive@example.com","personnel_id":17},"plants":[{"id":1,"name":"Guindy","entity_id":1}]}}
```

Mobile OTP login starts with `POST /auth/send-otp`:

```json
{"mobile":"+919876543210","device_name":"Android - Sales phone"}
```

It returns HTTP 202 with `data.otp_required: true`, `channel: "whatsapp"`, `challenge_id` and `expires_in: 300`, with no token. Call verification with `{"challenge_id":"returned-challenge","otp":"123456"}`. The code expires after five minutes, is single-use, and is bound to the registered mobile number, user and device name. A changed number invalidates the challenge. After five incorrect attempts, request a new code. Duplicate normalized mobile numbers are rejected until the administrator corrects the user records. Inactive/locked/deleted users cannot authenticate. Existing per-user OTP flags do not choose the method: the client offers email/password or mobile OTP as separate alternatives. Existing device tokens retain their session lifetime.

All other requests require:

```text
Accept: application/json
Authorization: Bearer <api_token>
Content-Type: application/json       # for JSON request bodies
X-Plant-Id: <accessible plant ID>     # for workspace/module requests
```

`GET /me`, `GET /plants` and `POST /auth/logout` do not require `X-Plant-Id`. Logout revokes only the bearer token used for that request. No cookies or CSRF token are required. Do not use the old shared-key or legacy user-detail routes for this app. Profiles use an explicit field whitelist and never expose password hashes, OTP secrets or API keys.

The plant header is required; the API does not silently choose plant 1 or change the user's saved defaults. A suspended entity, inactive plant, removed membership, inactive user or locked account cannot be used. Missing plant returns 422; inaccessible plant returns 403. Use `GET /plants` again when permissions or membership change.

## Permissions and record ownership

Permissions follow the ERP's singular/plural uppercase module conventions. The role for the selected entity and plant is resolved on every request. Membership alone does not grant module access. Ordinary executives see their assigned leads and activities, plus quotations/orders they created or whose `sales_executive_id` matches their personnel ID. Leads use `mm_users.id` for ownership; commercial documents use `mm_personnels.id`. These IDs are not interchangeable.

| Module | Required grants |
|---|---|
| CRM and follow-ups | `CRM_LEAD.VIEW`, `CREATE`, `UPDATE`, `DELETE`; `ASSIGN` for assigning another user; `CONVERT` for customer conversion |
| Quotations | `QUOTATION.VIEW`, `CREATE`, `UPDATE`, `DELETE`; `APPROVE` for review |
| Sales orders/progress | `SALES_ORDER.VIEW`, `CREATE`, `UPDATE`, `DELETE` |
| Customers | `PATRON.VIEW` |
| Batch schedules | `BATCHING_SCHEDULE.VIEW`, `CREATE`, `UPDATE`, `DELETE` |

`CRM_LEAD.VIEW_ALL`, `QUOTATION.VIEW_ALL`, `SALES_ORDER.VIEW_ALL`, and `PATRON.VIEW_ALL` extend visibility within the selected plant. They do not grant editing or cross-plant access. The migration grants these and `QUOTATION.APPROVE` to existing standard administrator and sales-manager role codes; ordinary executives do not receive them automatically. Use the ERP role/permission editor to grant the normal module actions needed by an executive. A reviewer normally needs `QUOTATION.VIEW_ALL` as well as `QUOTATION.APPROVE`. A submitter cannot review their own submission, even if they are an administrator.

Customers are scoped through the executive's leads, quotations and orders unless `PATRON.VIEW_ALL` is granted. Module permissions still apply when retrieving detail data. Foreign or unowned records return 404. Login, profile and plant discovery work even if the user has no business module permissions; dashboard omits modules they cannot view.

## Response and list conventions

Single-record success: `{"success":true,"data":{...}}`. Creation is normally 201; schedule creation reuses the existing scheduler and returns 200 with `schedule` instead of `data`. Schedule editing/deletion also retain existing scheduler response fields. Delete/logout responses contain `success` and `message`.

Lists return Laravel pagination under `data`:

```json
{"success":true,"data":{"current_page":1,"data":[],"per_page":20,"total":0,"last_page":1,"next_page_url":null}}
```

Use `page` and `per_page` (1–100, default 20). Follow `next_page_url` while retaining your authentication and plant headers. Decimal model fields may serialize as strings; treat them as monetary/quantity values rather than assuming every value is a JSON float.

HTTP 401 means invalid/expired authentication; 403 means account, plant or permission denial; 404 hides unavailable records; 409 means verification is already in progress (retry shortly); 422 means input or lifecycle validation failed; 429 means throttling; 503 means WhatsApp OTP submission unavailable. Field validation returns `errors` keyed by field name; use those keys to mark mobile inputs. Lifecycle failures may have only a `message`. Handle both shapes. Authenticated requests are limited to 120 per minute, password login to five per minute, OTP sends to five per minute per IP and three per minute per normalized mobile number, and OTP verification to ten per minute, with an additional five-attempt per-challenge limit. Send, verify and password login use separate throttle counters.

Use `YYYY-MM-DD` for dates. Supply explicit offsets for date/time values where possible. The scheduler stores selected planning times in its existing database timestamp convention; omitted/blank optional timestamps remain null. Use ERP timezone settings when presenting date/time values.

## Workspace and customers

| Method | Path | Result |
|---|---|---|
| GET | `/me` | Safe current user profile |
| GET | `/plants` | Accessible active plants |
| GET | `/dashboard` | Own-record metrics for permitted modules and `as_of` timestamp |
| GET | `/lookups` | Permitted customers, sites, active mix designs, sales taxes, customer POs, orders and scheduling resources |
| GET | `/marketing/summary` | Lead counts by source and site-visit activity count |
| GET | `/customers?search=Kavin&per_page=20` | Customer list |
| GET | `/customers/{id}` | Customer contacts and production progress, where permitted |

Lookup options are permission-dependent. Marketing enquiries are created as leads with `source: Campaign` or another existing source. Record field visits as follow-ups with `type: Site Visit`. Campaign automation and bulk messaging are not part of this API.

## Leads and follow-ups

| Method | Path | Action |
|---|---|---|
| GET | `/leads` | List; filters `search`, `status`, `page`, `per_page` |
| POST | `/leads` | Create |
| GET | `/leads/{id}` | Detail, activity timeline and deal |
| PUT/PATCH | `/leads/{id}` | Full form update |
| DELETE | `/leads/{id}` | Soft delete an unconverted lead |
| POST | `/leads/{id}/convert` | Convert a qualified lead to customer/deal |
| GET | `/follow-ups` | List; `view=today,upcoming,overdue,completed,all` |
| POST | `/follow-ups` | Create activity |
| PUT | `/follow-ups/{id}` | Edit unfinished activity |
| POST | `/follow-ups/{id}/complete` | Complete activity once; optional `description` outcome |
| DELETE | `/follow-ups/{id}` | Soft delete activity |

Lead create/update required fields: `contact_name`, `enquiry_date`, `source`, `priority`, `status`, `expected_value`, and either `phone` or `email`. Optional fields: `company_name`, `address`, `assigned_to`, `project_name`, `requirement`, `estimated_quantity`, `delivery_location`, `expected_purchase_date`, `remarks`, `lost_reason`.

```json
{"contact_name":"Arun Kumar","company_name":"Kavin Builders","phone":"9876543210","email":null,"enquiry_date":"2026-10-09","source":"Referral","priority":"High","status":"New","expected_value":200000,"requirement":"M25 Design","estimated_quantity":40}
```

Use the enums from `/lookups`. Valid unconverted lead statuses are New, Contacted, Qualified and Unqualified. Unqualified requires `lost_reason`. Converted status is assigned by conversion and cannot be manually selected for an unconverted lead. A converted lead must retain that status and cannot be deleted. Phone is normalized to digits and email to lowercase. Duplicate active lead phone/email is rejected within the plant.

`assigned_to` defaults to the signed-in user for new leads; changing ownership requires ASSIGN and an active owner belonging to the selected plant. Conversion body is `{"customer_id":null}` to create a customer or `{"customer_id":123}` to use an existing active customer. The CRM service enforces qualification, duplicate customer checks and contact/address setup.

Activity body:

```json
{"lead_id":12,"type":"Call","subject":"Confirm requirement","description":"Discuss delivery access","due_at":"2026-10-10T10:30:00+05:30"}
```

Types: Call, Meeting, Site Visit, Task, Note. `due_at` is required except for Note. Notes are completed immediately; other activities remain pending until completion. The activity owner follows the lead. System timeline events cannot be edited or deleted through follow-up endpoints. Updates require the full activity form.

## Quotations

| Method | Path | Action |
|---|---|---|
| GET | `/quotations` | List; `search` searches reference; optional `approval_state` |
| POST | `/quotations` | Create draft |
| GET | `/quotations/{id}` | Header, products, tax components, customer and site |
| PUT/PATCH | `/quotations/{id}` | Full draft form update |
| DELETE | `/quotations/{id}` | Soft delete an editable draft |
| POST | `/quotations/{id}/submit` | Submit for another user's review |
| POST | `/quotations/{id}/review` | Approve/reject submitted quotation |

```json
{"patron_id":5,"site_id":10,"quote_date":"2026-10-09","validity_date":"2026-10-16","is_tax_inclusive":true,"notes":"Delivery subject to scheduling","items":[{"mix_design_id":4,"quantity":3.55,"rate":546,"tax_id":36}]}
```

Required: customer `patron_id`, `site_id`, `quote_date`, `is_tax_inclusive` and 1–100 `items`. Validity date must be on/after quote date. Optional `reference` is unique among active quotations within the plant; otherwise numbering uses the ERP quotation prefix. Item fields: `mix_design_id`, `quantity`, `rate`, optional `tax_id`, `uom_id`, `concrete_pump`, `pump_rate`. An item has no manually supplied ID in this API; editing replaces its lines using the existing quotation model. `pump_rate` is the existing flat line charge, not a charge multiplied by quantity.

The server selects only active Sales parent taxes from the selected plant, calculates the base using the shared ERP print calculation, rounds tax components separately, sums their amounts, then applies a document adjustment to the nearest whole rupee. Submitted client totals/status/owner are not trusted. For the example, base 1,642.63 plus CGST 147.84 plus SGST 147.84 gives 1,938.31; adjustment -0.31 produces stored whole-rupee total 1,938.00. Tax-inclusive unrounded selling value is 1,938.30. This distinguishes component reconciliation from whole-rupee document rounding.

States: `draft → pending → approved` or `rejected`. Pending/approved quotations cannot be edited/deleted by mobile. Editing a rejected quotation resets it to draft. Approved/rejected decisions also map to the ERP's Accepted/Rejected status. Accepted or converted ERP quotations cannot be overwritten through mobile. Converted PO links are retained. Approval body: `{"decision":"approved","comment":"Rates approved"}`; rejection requires a comment. Review requires a different user and is allowed once per pending submission. Approval audit fields retain submitter/reviewer IDs and timestamps.

Customer PO conversion and quotation PDF/email delivery remain established ERP workflows; they are not exposed as new mobile endpoints in this version. Approval does not automatically create a PO or order.

## Orders and production progress

| Method | Path | Action |
|---|---|---|
| GET | `/orders` | List; `search` searches order number; optional `customer_id` |
| POST | `/orders` | Create against an existing customer PO |
| GET | `/orders/{id}` | Order and progress |
| PUT/PATCH | `/orders/{id}` | Full form update before operations start |
| DELETE | `/orders/{id}` | Soft delete before operations start |
| GET | `/orders/{id}/batches` | Paged batch history, batch number ascending |
| GET | `/orders/{id}/dispatches` | Paged trips, dispatch time ascending |

```json
{"customer_id":5,"site_id":10,"mix_design_id":4,"customer_po_id":7,"total_qty":40,"scheduled_start":null,"scheduled_end":null}
```

Customer and site must match the PO, and the mix design must be one of its lines. Price, sales tax, tax-inclusive flag and pump charge are copied from that PO line. The server generates the order number and starts it as Confirmed with zero produced quantity. Clients cannot submit production quantities or operational statuses. Ownership is checked for the PO. An order with batches, dispatches or schedule slots cannot be edited/deleted through this API. Completed and cancelled orders are also protected.

Progress fields: `ordered_m3`, `batch_count`, `completed_batches`, `cancelled_batches`, `committed_m3`, `dispatched_m3`, `delivered_m3`, `remaining_delivery_m3`. Committed quantity includes planned batches and excludes cancelled/deleted batches. Completed count uses the explicit completed batch status. Dispatched quantity excludes Draft, Loading, Cancelled and deleted trips, and trips whose linked batch was deleted. Delivered quantity uses Delivered/Completed trip status. Invoicing alone does not establish delivery. Remaining delivery is ordered minus delivered, floored at zero. Customer progress sums accessible orders; it does not include orders owned by another executive without VIEW_ALL.

## Batch schedules

| Method | Path | Action |
|---|---|---|
| GET | `/batch-schedules` | List; optional `schedule_date` |
| POST | `/batch-schedules` | Create a planned slot |
| GET | `/batch-schedules/{id}` | Planned and linked operational details |
| PUT | `/batch-schedules/{id}` | Edit an unlinked planned slot |
| DELETE | `/batch-schedules/{id}` | Soft delete an unlinked planned slot |

Required body: `schedule_date`, `sales_order_id`, `site_id`, `mix_design_id`, `qty_m3`, `order_volume_m3`, `pump_type`. Optional: `pour_reference`, `vehicle_id`, `driver_id`, `pump_vehicle_id`, `notes`, `batching_time`, `dispatch_time`, `eta_site`, `unloading_start`, `unloading_end`. Null optional times remain null. All IDs must be active records in the selected plant, and the order must be visible to the executive. Use the supplied `pump_types` lookup.

The order must be Scheduled/Confirmed or In Progress, and its site and mix design must match the slot. The existing scheduler performs quantity/capacity, chronology, mixer, driver and pump conflicts. Show its 422 field errors in the app. Creating a slot does not create a batch. Mobile accepts only `status: scheduled` and forbids dispatch linking. A linked batch/dispatch or later operational status prevents mobile editing/deletion. There is no mobile endpoint to mark Loading, Dispatched or Delivered or generate an invoice; existing production and Save Dispatch actions retain that responsibility.

## Integration limits and changed files

Validation: 15 isolated mobile API feature tests passed with 109 assertions, covering password login/revocation, passwordless WhatsApp OTP, expired codes and attempt limits, provider rejection, duplicate/changed mobile numbers, plant and ownership restrictions, tax totals, manager approval, order mutation restrictions, scheduler capacity and lifecycle, dropdowns, dashboard/marketing, migration up/down and mobile route compilation. Provider HTTP requests are mocked; no real WhatsApp message was sent. PHP syntax checks passed for the API files, model change and route registration. Both Postman exports parse as JSON and contain 45 requests covering all 41 mobile routes. MySQL deployment remains unverified until the migration is applied on a reachable database. The project's existing web routes have a duplicate `verification.notice` name; mobile routes compile independently, but a full-project route cache requires resolving that existing conflict.

The API is online-only: no offline mutation receipt/idempotency store, push notification service, attachment endpoint or mobile frontend connection was added. After a timeout during creation, check the list before retrying to avoid duplicates. Follow-up completion is naturally repeatable; document creation is not. The Sites preview remains a demonstration until a mobile client is wired to these endpoints.

Implementation files:

- `routes/sales-mobile.php`; registered from `routes/mobileapp.php`.
- `app/Http/Middleware/MobileDeviceToken.php`, `MobilePlantContext.php`.
- `app/Http/Controllers/Api/SalesMobile/AuthController.php`, `LeadController.php`, `QuotationController.php`, `OrderController.php`, `ScheduleController.php`, `WorkspaceController.php`.
- `app/Services/Mobile/MobileAccess.php`, `QuotationCalculator.php`, `MobileOtpSender.php`.
- `config/services.php` WhatsApp OTP template and country-code settings.
- `app/Models/Quotation.php` approval timestamp casts.
- `database/migrations/2026_10_09_180500_add_sales_mobile_quotation_approval.php`.
- `tests/Feature/SalesMobileApiTest.php`.
- `tools/build-sales-mobile-postman.cjs`, both Postman files and this guide.
- `docs/file-authorship.md` records comment-free artifact metadata.
