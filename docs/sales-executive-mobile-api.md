<!--
Author: ragul-onemodo
Created: 2026-10-09 18:13:46 Asia/Calcutta (UTC+05:30)
-->

# Sales Executive Mobile API v1

These Laravel JSON APIs support the sales executive mobile screens: dashboard, leads, marketing enquiries and visits, follow-ups, customers, quotations, sales orders, production progress and batch schedules. They are separate from the existing mobile dashboard routes and web/Inertia pages. The published Sites screen demonstration does not call these APIs yet.

## Mobile endpoint regression verification

The regression suites cover `routes/mobileapp.php`, including its imported `/v1` routes. All protected routes are exercised without a token and with an invalid token; all `/v1` plant-scoped routes are exercised with missing and inaccessible plant headers. Successful lifecycles cover legacy login/OTP/user management, leads and conversion, follow-ups, customers/sites, quotations and approval, orders, batch/dispatch history, and schedule CRUD. Dedicated catalog and OTP suites cover their additional cases.

Boundary cases include pagination limits, empty searches, missing/non-numeric IDs, invalid dates and payloads, duplicate users/leads, foreign/unowned records, deleted batches and dispatches, repeated deletion, completed/linked record protections, approval transitions, expired/replayed OTPs, account revocation, rate limits, and concise errors even when debug mode is enabled.

Run from the repository root:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/SalesMobileApiTest.php tests/Feature/Api/SalesMobileCatalogTest.php tests/Feature/Api/SalesMobileOtpTest.php tests/Feature/Api/SalesMobileSitesTest.php tests/Feature/Api/MobileLoginApiTest.php
```

Tests use isolated SQLite databases and fake external notifications. Legacy report endpoints use a mocked `DashboardService` to verify filter forwarding, empty responses and server failures; they do not validate production report SQL, financial totals, or report tenancy. Real WhatsApp/email delivery and concurrent MySQL transactions require separate integration verification. The legacy email OTP implementation currently caches a code and logs it locally; it does not invoke an email delivery transport.

## Deployment

Use the ERP server as the API host. The Sites preview address is not an API host. The base path is `/api/mobile/v1`; for example `https://your-erp.example/api/mobile/v1`.

Run on the Laravel application server:

```sh
php artisan permission:cache-reset
php artisan optimize:clear
php artisan route:list --path=api/mobile/v1
```

The new migration `2026_10_09_180500_add_sales_mobile_quotation_approval.php` adds six approval audit fields to `mm_quotations` and five permissions. Existing CRM, schedule, batch and Sanctum migrations must already be installed. Existing quotations start with mobile approval state `draft`; an already accepted or converted ERP quotation remains protected by its existing status and PO links. No existing accounting data is rewritten. Rollback removes the six fields, including their approval history; it retains permission grants because they might have existed before this migration.

Configure a reliable shared cache with atomic lock support for OTP challenges, single-use verification and rate limiting. Mobile WhatsApp OTP is handled directly in AuthController.php by its sendWhatsAppTemplate() cURL function, without intermediate sender services. Batches/Dispatch retains its existing TendigitWhatsAppClient. Both use TENDIGIT_LICENSE_NUMBER, TENDIGIT_API_KEY and the configurable HTTPS TENDIGIT_ENDPOINT (default https://app.tendigit.in/api/sendtemplate.php). Dispatch keeps its fourteen-parameter modormc_dispatch template; mobile login uses sales_executive_login with its single OTP parameter. Set `TENDIGIT_LICENSE_NUMBER` and `TENDIGIT_API_KEY`, with approved login template `MOBILE_WHATSAPP_OTP_TEMPLATE` (default `sales_executive_login`). It takes one positional parameter: the six-digit OTP as `{{1}}`. The message is `*{{1}}* is your verification code.`. `MOBILE_OTP_COUNTRY_CODE` defaults to `91` for ten-digit local numbers; international numbers must include the country code. The user must have a uniquely registered `mm_users.mobile` number with WhatsApp enabled. A provider rejection or missing credentials returns 503 without creating a token. HTTP 202 confirms provider submission, not final WhatsApp delivery. Live delivery requires valid provider credentials and an approved template and was not exercised by the automated tests.

Deploy over HTTPS. Device tokens expire after 30 days, or sooner if Sanctum's global expiration setting requires it. Keep tokens in the mobile platform's secure storage. Reauthenticate after expiry; this API has no refresh-token endpoint. Administrators can revoke a lost device's Sanctum personal access token.

The development machine's MySQL connection was unavailable during implementation, so this migration still needs to be applied to that database. Automated tests use an isolated in-memory SQLite database and do not write production records.

## Postman setup

The bundled local environment defaults to `http://127.0.0.1:8000`. Use **POST** for `/auth/send-otp`. Calling `http://localhost` without the port can reach the WAMP default site instead and return route not found. Override `base_url` with the correct ERP origin when testing another server.

Before testing either login method, an administrator must grant mobile app access for the account's email in **Membership → Users → Mobile App Access**. This applies to every role, including system administrators. A mobile access grant permits authentication; existing module permissions still determine the data and actions available.

Import both files:

- `docs/postman/sales-executive-mobile.postman_collection.json`
- `docs/postman/sales-executive-mobile.postman_environment.json`

Select **MODO Sales Mobile - Local** and set `base_url` to the ERP origin without a trailing slash and `plant_id`. Choose one authentication path:

- **Email/password:** set `email` and `password`, then run **Login**. Its 200 response stores the bearer token in `api_token` directly.
- **Mobile/WhatsApp OTP:** set `mobile`, then run **Send WhatsApp OTP**. Its 202 response stores `challenge_id` and clears the previous token/OTP. Enter the received WhatsApp code in `otp` and run **Verify OTP** to store the bearer token. No email or password is required for this path.

After either path, run **Accessible plants** and **Dropdown lookups**.

Copy actual IDs from dropdown responses into `customer_id`, `customer_po_id`, `site_id`, `mix_design_id` and `sales_tax_id`. Create requests save their returned lead, activity, quotation, order or schedule ID automatically. The collection uses `today` and `validity_date` variables; clear or update them on a different testing day. All example people and values are demonstration data.

Mobile approvals require a different submitter but the quotation must be assigned to the logged-in reviewer; cross-owner approval uses the ERP web workflow. For an assigned reviewer, use a different reviewer account. Set `reviewer_email` and `reviewer_password` and run **Reviewer login**, or set `reviewer_mobile`, run **Reviewer send WhatsApp OTP**, enter the code in `otp` and run **Reviewer verify OTP**. Review requests use `reviewer_token`. Complete executive verification before starting reviewer OTP because both use the `challenge_id` and `otp` variables. The reviewer requires `QUOTATION.APPROVE` and access to the quotation. Approval and rejection are alternatives; do not run both on the same submitted quotation. Likewise, do not run the whole collection blindly: the delete folder soft-deletes records, converted leads cannot be deleted, and logout must run last. Run destructive actions individually against disposable records.

The collection and environment contain no real credentials. Exporting a populated environment can include tokens/passwords, so remove those values before sharing it. To regenerate the collection after intentional API changes, run `node tools/build-sales-mobile-postman.cjs`.

## Swagger alternative to Postman

Open `/api/mobile/v1/docs` on the ERP host. The local preview for this repository is `http://127.0.0.1:8000/api/mobile/v1/docs`. The page uses the existing bundled Swagger UI assets. Download the portable OpenAPI 3.0.3 definition from `/api/mobile/v1/openapi.json` or `docs/openapi/sales-executive-mobile.openapi.json`.

For WhatsApp login in Swagger:

1. Ensure the registered account has Mobile App Access granted.
2. Expand **Authentication → Send WhatsApp OTP**, select **Try it out**, enter `mobile`, then **Execute**.
3. Copy the returned `data.challenge_id` into **Verify OTP**, add the received six-digit code, and execute it.
4. Copy `data.token` into **Authorize** without adding the word `Bearer`. Provide `X-Plant-Id` when testing workspace endpoints.

Email/password **Login** is the alternative and returns a token directly. Swagger does not preserve authentication across reloads and does not run requests automatically. The definition contains 44 HTTP operations, including PATCH variants for full-form resource updates. Administrator grant/removal remains an ERP website action with session/CSRF protection.

To regenerate after API changes, run `node tools/build-sales-mobile-postman.cjs` then `node tools/build-sales-mobile-openapi.cjs`. No additional migration or Swagger package installation is required for these documentation routes; deploy the Blade view, controller, routes and generated JSON together.

### Live WhatsApp test limitation

The October 12 local live test was blocked before provider submission: cURL reported `SSL: no alternative certificate subject name matches target host name 'app.tendigit.in'`. Tendigit must repair its certificate or provide a verified HTTPS API endpoint. Keep TLS certificate verification enabled. No OTP challenge or token is returned when submission fails. Certificate failures return HTTP 503 with error_code WHATSAPP_TLS_ERROR, network failures WHATSAPP_CONNECTION_ERROR, and provider rejection WHATSAPP_SUBMISSION_FAILED. These handled errors do not expose an exception or stack trace. Contact [Tendigit support](https://www.tendigit.in/contact.php) to resolve this provider endpoint issue before retrying live login. The provider-response parser also now checks the response message, so a successful JSON response containing `error: false` is not misclassified as an error.

## Authentication and headers

Public requests:

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/login` | Email/password login; issues a device token directly |
| POST | `/auth/send-otp` | Mobile-number login; sends WhatsApp OTP without a password |
| POST | `/auth/verify-otp` | Verifies the mobile OTP challenge and issues a device token |

Login JSON:

```json
{"email":"executive@example.com","password":"your-password"}
```

Successful password login or mobile OTP verification is HTTP 200:

```json
{"success":true,"data":{"token":"device-bearer-token","token_type":"Bearer","expires_at":"2026-11-08T10:00:00+05:30","user":{"id":12,"name":"Sales Executive","email":"executive@example.com","personnel_id":17},"plants":[{"id":1,"name":"Guindy","entity_id":1}]}}
```

Mobile OTP login starts with `POST /auth/send-otp`:

```json
{"mobile":"+919876543210"}
```

It returns HTTP 202 with `data.otp_required: true`, `channel: "whatsapp"`, `challenge_id` and `expires_in: 300`, with no token. Call verification with `{"challenge_id":"returned-challenge","otp":"123456"}`. The code expires after five minutes, is single-use, and is bound to the registered mobile number and user. A changed number invalidates the challenge. After five incorrect attempts, request a new code. Duplicate normalized mobile numbers are rejected until the administrator corrects the user records. Inactive/locked/deleted users cannot authenticate. `mm_users.is_otp_enabled` grants access to both login methods: the client offers email/password or mobile OTP as separate alternatives. Existing device tokens retain their session lifetime.

All other requests require:

```text
Accept: application/json
Authorization: Bearer <api_token>
Content-Type: application/json       # for JSON request bodies
X-Plant-Id: <accessible plant ID>     # for workspace/module requests
```

`GET /me`, `GET /plants` and `POST /auth/logout` do not require `X-Plant-Id`. Logout revokes only the bearer token used for that request. No cookies or CSRF token are required. Do not use the old shared-key or legacy user-detail routes for this app. Profiles use an explicit field whitelist and never expose password hashes, OTP secrets or API keys.

The plant header is required; the API does not silently choose plant 1 or change the user's saved defaults. System administrators (including Super Admin) can select any existing, non-deleted plant without membership or module-permission restrictions, including inactive plants and plants under suspended entities. Other users require a current entity-wide or plant-specific membership, an active plant, a non-suspended entity, and the relevant role or direct module permissions. Account and device-token checks still apply to all users. Missing plant header returns 422; inaccessible plant returns 403. Use `GET /plants` again when permissions or membership change.

## Permissions and record ownership

### Per-user mobile access

Mobile app access is controlled per user email, independently of user group/role. System administrators can manage any user, including their own email. An entity administrator can manage members of the active entity, but cannot change a system administrator's access. Ordinary users cannot grant access to themselves.

Use **Membership → Users → Mobile App Access**, enter the registered email, and select **Grant Access** or **Remove Access**. The users list shows **Allowed / No Access**. The authenticated ERP endpoint is `PUT /membership/users/mobile-access` with `{"email":"user@example.com","enabled":true}` (or `false`); it uses the website session and CSRF protection, rather than a mobile bearer token.

Mobile access now uses the existing `mm_users.is_otp_enabled` column: **1 = allowed, 0 = denied**. The create/edit user switch and email-based grant/remove controls update the same field. The response returns `data.is_otp_enabled`. Only administrators can change access. `mobile_access_revision` remains to invalidate pending challenges when the flag changes.

The migration below preserves existing grants (either the old mobile flag or the OTP flag enabled), then removes `mobile_access_enabled`. It also creates the revision column if absent and has no CRM dependency. Rollback recreates the old flag from current OTP access values. Deploy with:

```sh
php artisan migrate --path=database/migrations/2026_10_12_153300_use_otp_enabled_for_mobile_access.php --force
php artisan optimize:clear
npm run build
```

For a fresh installation, run all migrations in their normal order. Do not rerun the earlier mobile-access migration after applying this replacement.

Removal blocks both email/password and mobile/WhatsApp OTP login immediately, revokes the account's sales-mobile tokens, and blocks subsequent authenticated mobile requests. Existing ERP website sessions remain available. Pending OTP challenges become invalid even if access is granted again; the user must request a fresh code. The grant does not create memberships, change roles or bypass module permissions. User activity status and account lock checks remain enforced.

Permissions follow the ERP's singular/plural uppercase module conventions. The role for the selected entity and plant is resolved on every request. Membership alone does not grant module access. Every mobile login, including managers, administrators and system administrators, is restricted to their assigned leads and activities and to quotations, customer POs and orders whose `sales_executive_id` matches their personnel ID in that plant. Creation alone does not grant document access. Mobile ownership does not depend on a sales-executive role code. A missing personnel assignment exposes no commercial records and blocks commercial creation. Leads use `mm_users.id` for ownership; commercial documents use `mm_personnels.id`. These IDs are not interchangeable. Follow-up lists, dashboard/marketing counts, next-follow-up dates and nested lead activities also exclude tasks explicitly assigned to another user. Legacy unassigned activities remain visible only through the executive's own lead. Another executive's activity returns 404 for detail/update/complete/delete attempts.

| Module | Required grants |
|---|---|
| CRM and follow-ups | `CRM_LEAD.VIEW`, `CREATE`, `UPDATE`, `DELETE`; assignment remains limited to the logged-in user; `CONVERT` for customer conversion |
| Quotations | `QUOTATION.VIEW`, `CREATE`, `UPDATE`, `DELETE`; `APPROVE` for review |
| Sales orders/progress and customer PO listing | No module action permission required; authenticated mobile access and plant membership required. Ownership scope remains; `SALES_ORDER.VIEW_ALL` does not broaden mobile ownership. |
| Customers | `PATRON.VIEW` |
| Sites | `SITE.VIEW` |
| Batch schedules | No module action permission required; authenticated mobile access, plant membership and sales order ownership scope remain required. |

`CRM_LEAD.VIEW_ALL`, `QUOTATION.VIEW_ALL`, `SALES_ORDER.VIEW_ALL`, `PATRON.VIEW_ALL`, and administrator status never broaden mobile record ownership. These grants may still affect the separate ERP web workflow. Mobile leads cannot be assigned to another user. For cross-owner approval, use the authorized ERP web review workflow; mobile review first requires the quotation to belong to the logged-in reviewer.

Executives can see customers assigned directly through `mm_patrons.sales_executive_id`, or related through their visible leads, quotations, customer POs or orders. Direct assignments support the first sale without requiring prior documents. The sites list, detail and lookup endpoints expose all non-deleted sites in the selected plant, independent of user or customer relationships. Quotations can select these shared sites. Order and schedule writes retain their business relationship checks. Schedules and production history inherit access through the visible sales order. Managers and administrators follow the same customer ownership rules and plant-wide site lookup scope. Module permissions still apply when retrieving detail data. Foreign records and unowned personal business records return 404. Login, profile and plant discovery work even if the user has no business module permissions; dashboard omits modules they cannot view.

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
| GET | `/plants` | All existing plants for system administrators; assigned active plants under non-suspended entities for other users |
| GET | `/dashboard` | Own-record metrics for permitted modules and `as_of` timestamp |
| GET | `/lookups` | Permitted customers, sites, active mix designs, sales taxes, customer POs, orders and scheduling resources |
| GET | `/mix-designs?search=M25&per_page=20` | Active mix designs in the selected plant; search by name or code; includes grade, unit and rate |
| GET | `/taxes?search=GST&per_page=20` | Active parent sales taxes in the selected plant, with active sales-tax children |
| GET | `/marketing/summary` | Lead counts by source and site-visit activity count |
| GET | `/customers?search=Kavin&per_page=20` | Customer list |
| GET | `/customers/{id}` | Customer contacts and production progress, where permitted |
| GET | `/sites?search=Central&per_page=20` | Paginated sites in the selected plant; optional name search, `per_page` 1–100 (default 20), `page` for subsequent pages |
| GET | `/sites/{id}` | Site fields; missing, deleted or foreign-plant IDs return 404 |

Site responses use `{ "success": true, "data": ... }`: the list data contains the paginator and its site array; detail data contains one site. Both require `SITE.VIEW` and exclude soft-deleted sites. Sites are shared across users in the selected plant, including sites with no customer or sales relationship. The site options in `/lookups` use the same plant-wide scope.

Mix-design and tax lists use the same paginated response format, with `per_page` 1–100 (default 20) and `page` for subsequent pages. Both require a bearer token and `X-Plant-Id`. Mix designs require any of `MIX_DESIGN.VIEW`, `CRM_LEAD.VIEW`, `QUOTATION.VIEW`, or `SALES_ORDER.VIEW`. Taxes require any of `TAX.VIEW`, `QUOTATION.VIEW`, or `SALES_ORDER.VIEW`. Deleted records are excluded; tax children are restricted to active sales taxes in the selected plant. Use the parent tax ID for quotation items.

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

Use `mix_design_id` to submit the selected plant mix design ID, rather than its name or label. Responses include `mix_design_id` and `mix_design` (`id`, `design_name`). The legacy `requirement` input is rejected.

Lead create/update required fields: `contact_name`, `enquiry_date`, `source`, `priority`, `status`, `expected_value`, and either `phone` or `email`. Optional fields: `company_name`, `address`, `assigned_to`, `project_name`, `mix_design_id`, `estimated_quantity`, `delivery_location`, `expected_purchase_date`, `remarks`, `lost_reason`.

```json
{"contact_name":"Arun Kumar","company_name":"Kavin Builders","phone":"9876543210","email":null,"enquiry_date":"2026-10-09","source":"Referral","priority":"High","status":"New","expected_value":200000,"mix_design_id":4,"estimated_quantity":40}
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

Every mobile quotation operation, including review by administrators, first applies the logged-in user's ownership scope. `VIEW_ALL`, creator identity, or system-admin status cannot expose another user's quotation; inaccessible IDs return 404. Existing submission-state and self-review checks still apply to ordinary reviewers. The established system-admin review exception applies only to quotations already owned by that logged-in administrator. For cross-owner review, use the authorized ERP web workflow rather than these mobile endpoints. Review validation and rejection comments remain required.

```json
{"patron_id":5,"site_id":10,"quote_date":"2026-10-09","validity_date":"2026-10-16","is_tax_inclusive":true,"notes":"Delivery subject to scheduling","items":[{"mix_design_id":4,"quantity":3.55,"rate":546,"tax_id":36}]}
```

Quotation list, detail, create, update, submit and review responses include `items` with `mix_design_id`, `quantity`, `rate`, `uom`, `discount_type`, `discount`, `discount_amount`, `tax_id`, `tax` (including children), `tax_amount`, `untaxed_amount`, `amount_total`, `concrete_pump` and `pump_rate`. Each line's `mix_design` includes its name/code, catalog rate, `concrete_grade`, `unit`, and component `items`. Each component includes `product`, `uom`, `actual_quantity`, `cross_quantity`, `variation_quantity` and `rate`. Quotation quantities/rates are the agreed selling values; component quantities/rates describe the current mix recipe, and `rate_per_qty` is the current catalog rate. They are not historical recipe snapshots. Related records may be null when unavailable; decimal values may serialize as strings. Lists retain Laravel pagination (`data.data`); individual responses use `data`.

Example line (abbreviated):

```json
{"mix_design_id":4,"quantity":"3.550","rate":"546.00","discount_amount":"0.00","tax_amount":"295.68","untaxed_amount":"1642.63","amount_total":"1938.31","mix_design":{"id":4,"design_name":"M25","design_code":"M25-01","rate_per_qty":"546.0000","concrete_grade":{"id":2,"name":"M25"},"items":[{"product":{"id":8,"title":"Cement"},"actual_quantity":"300.0000","rate":"6.0000"}]}}
```

Required: customer `patron_id`, `site_id`, `quote_date`, `is_tax_inclusive` and 1–100 `items`. Validity date must be on/after quote date. Optional `reference` is unique among active quotations within the plant; otherwise numbering uses the ERP quotation prefix. Item fields: `mix_design_id`, `quantity`, `rate`, optional `tax_id`, `uom_id`, `concrete_pump`, `pump_rate`. An item has no manually supplied ID in this API; editing replaces its lines using the existing quotation model. `pump_rate` is the existing flat line charge, not a charge multiplied by quantity.

The server selects only active Sales parent taxes from the selected plant, calculates the base using the shared ERP print calculation, rounds tax components separately, sums their amounts, then applies a document adjustment to the nearest whole rupee. Submitted client totals/status/owner are not trusted. For the example, base 1,642.63 plus CGST 147.84 plus SGST 147.84 gives 1,938.31; adjustment -0.31 produces stored whole-rupee total 1,938.00. Tax-inclusive unrounded selling value is 1,938.30. This distinguishes component reconciliation from whole-rupee document rounding.

States: `draft → pending → approved` or `rejected`. Pending/approved quotations cannot be edited/deleted by mobile. Editing a rejected quotation resets it to draft. Approved/rejected decisions also map to the ERP's Accepted/Rejected status. Accepted or converted ERP quotations cannot be overwritten through mobile. Converted PO links are retained. Approval body: `{"decision":"approved","comment":"Rates approved"}`; rejection requires a comment. Ordinary reviewers require a different submitter and a pending quotation assigned to themselves. The existing system-admin state/self-review exception never bypasses ownership. Approval audit fields retain submitter/reviewer IDs and timestamps.

Customer PO conversion and quotation PDF/email delivery remain established ERP workflows; they are not exposed as new mobile endpoints in this version. Approval does not automatically create a PO or order.

### Create a quotation from a lead

`POST /api/mobile/v1/leads/{lead}/quotation` uses the same device token and `X-Plant-Id` headers. It requires CRM lead VIEW access, and the lead must belong to the logged-in user regardless of role. New, Contacted and Qualified leads are converted automatically: a patron with type Customer, an active status, a generated code, a primary contact and an address (when supplied on the lead) is created along with the sales opportunity. Contact and address types must be configured. Duplicate patrons require selecting an existing accessible active customer with `patron_id`. Unqualified leads cannot be converted automatically. An active plant mix design (`mix_design_id`) is required. Already converted leads reuse their customer and opportunity; an existing linked quotation blocks creation.

```json
{"site_id":10,"tax_id":36,"is_tax_inclusive":true}
```

Defaults: customer and mix design come from the lead, quantity from `estimated_quantity`, rate from the current mix design `rate_per_qty`, unit from the mix design, and quote date from today. An explicit `site_id` may select any non-deleted site in the selected plant. Otherwise the site name comes from the project, delivery location or customer name. A same-name site in the plant is reused regardless of customer or user ownership; matching ignores case and surrounding whitespace. If no match exists, the customer's sole site is used. Otherwise an active unloading site is created with the customer ID array and delivery address (falling back to the lead address). Deleted and foreign-plant sites are excluded. Already converted leads with multiple customer sites and no name match require an explicit selection. Notes include the lead number, project, delivery location and remarks unless overridden. `expected_value` is not treated as a unit rate.

Optional `patron_id` accepts an accessible active customer ID for automatic conversion; for an already converted lead it must match the lead customer. The customer name is returned as `data.patron.legal_name`; do not send a name in `patron_id`.

Lead-to-quotation creation records the logged-in user ID in `created_by` and does not require a personnel assignment. Access to the linked quotation follows the lead's `assigned_to` user. If a personnel record exists, `sales_executive_id` retains that personnel ID; otherwise it is null. User IDs are never written into the personnel foreign key. Linked quotations remain readable and editable by the lead owner without a personnel record.

Optional overrides: `quantity`, `rate`, `uom_id`, `quote_date`, `validity_date`, `reference`, `notes`, `is_tax_inclusive`, `tax_id`, `concrete_pump`, `pump_rate`, `discount_type`, `discount`. Tax-exclusive pricing is the default and no tax is assumed unless `tax_id` is supplied. Arbitrary `items`, `mix_design_id` and `sales_executive_id` overrides are rejected. The shared quotation validator/calculator still validates every input and calculates totals.

Success returns HTTP 201 with the same detailed quotation under `data`, including priced items and mix design details. The logged-in user is recorded as its creator; a personnel ID is included only when available. Its ID is stored in the lead opportunity's `quotation_id`, and a system activity records creation. The opportunity stage is unchanged because the quotation has not been sent. A linked quotation blocks repeated creation with HTTP 422; edit the existing quotation instead. Validation failures roll back conversion, customer/site creation and linkage. Another user's lead returns 404. Invalid customer/mix/opportunity, ambiguous site, invalid quantity/rate or invalid pricing fields return 422 with field errors.

## Orders and production progress

| Method | Path | Action |
|---|---|---|
| GET | `/orders` | List; `search` searches order number; optional `customer_id` |
| GET | `/customer-pos` | Paged customer POs with customer (`patron`), site and items; optional `search` (ERP/customer reference), `customer_id`, `site_id`, `status` (0 Draft, 1 Confirmed, 2 Completed), `per_page` (1–100, default 20) |
| POST | `/orders` | Create with an optional customer PO; `rate` is required without a PO |
| GET | `/orders/{id}` | Order and progress |
| PUT/PATCH | `/orders/{id}` | Full form update before operations start |
| DELETE | `/orders/{id}` | Soft delete before operations start |
| GET | `/orders/{id}/batches` | Paged batch history within the selected plant, batch number then ID ascending; only ID, batch number, order ID, size, status, start/end times |
| GET | `/orders/{id}/dispatches` | Paged trips, dispatch time ascending |

Customer PO results include only `id`, `reference`, `customer_po_reference`, `patron_id`, `site_id`, `order_date`, `status`, `is_tax_inclusive`, totals (`amount_untaxed`, `amount_tax`, `amount_total`), `patron` (ID/name), `site` (ID/name), and `items`. Each item includes `id`, `mix_design_id`, `quantity`, `rate`, `tax_id`, `concrete_pump`, `pump_rate`, and its three totals. Audit fields and internal conversion fields are omitted.

```json
{"customer_id":5,"site_id":10,"mix_design_id":4,"customer_po_id":7,"total_qty":40,"scheduled_start":null,"scheduled_end":null}
```

`customer_po_id` is optional or nullable. Without a PO, submit `rate` and optionally `tax_id`, `is_tax_inclusive`, `concrete_pump`, and `pump_rate`. When a PO is supplied, customer and site must match it and the mix design must be one of its lines; price, sales tax, tax-inclusive flag and pump charge are copied from that line. Omitting `customer_po_id` during an update preserves the existing link; explicit null removes it and requires a rate. The server generates the order number and starts it as Confirmed with zero produced quantity. Clients cannot submit production quantities or operational statuses. Ownership is checked for the PO. An order with batches, dispatches or schedule slots cannot be edited/deleted through this API. Completed and cancelled orders are also protected.

Progress fields: `ordered_m3`, `batch_count`, `completed_batches`, `cancelled_batches`, `committed_m3`, `dispatched_m3`, `delivered_m3`, `remaining_delivery_m3`. Committed quantity includes planned batches and excludes cancelled/deleted batches. Completed count uses the explicit completed batch status. Dispatched quantity excludes Draft, Loading, Cancelled and deleted trips, and trips whose linked batch was deleted. Delivered quantity uses Delivered/Completed trip status. Invoicing alone does not establish delivery. Remaining delivery is ordered minus delivered, floored at zero. Customer progress sums accessible orders. No mobile user includes another user's orders, including managers/admins with SALES_ORDER.VIEW_ALL.

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

Validation: 26 isolated mobile API feature tests passed with 164 assertions, covering password login/revocation, passwordless WhatsApp OTP, expired codes and attempt limits, provider rejection, duplicate/changed mobile numbers, plant and ownership restrictions, tax totals, manager approval, order mutation restrictions, scheduler capacity and lifecycle, dropdowns, dashboard/marketing, migration up/down and mobile route compilation. Provider HTTP requests are mocked; no real WhatsApp message was sent. PHP syntax checks passed for the API files, model change and route registration. Both Postman exports parse as JSON and contain 45 requests covering all 41 business API routes. The OpenAPI 3.0.3 schema validates, and Swagger UI, its assets and specification return HTTP 200 on the local preview. The OTP-access replacement migration is applied and verified on local MySQL; production deployment and the separate quotation-approval migration remain pending. The project's existing web routes have a duplicate `verification.notice` name; mobile routes compile independently, but a full-project route cache requires resolving that existing conflict.

The API is online-only: no offline mutation receipt/idempotency store, push notification service, attachment endpoint or mobile frontend connection was added. After a timeout during creation, check the list before retrying to avoid duplicates. Follow-up completion is naturally repeatable; document creation is not. The Sites preview remains a demonstration until a mobile client is wired to these endpoints.

Implementation files:

- `routes/sales-mobile.php`; registered from `routes/mobileapp.php`.
- `app/Http/Middleware/MobileDeviceToken.php`, `MobilePlantContext.php`.
- `app/Http/Controllers/Api/SalesMobile/AuthController.php`, `LeadController.php`, `QuotationController.php`, `OrderController.php`, `ScheduleController.php`, `WorkspaceController.php`.
- `app/Services/Mobile/MobileAccess.php`, `QuotationCalculator.php`.
- `config/services.php` WhatsApp OTP template and country-code settings.
- `app/Http/Controllers/UserMobileAccessController.php`, `UserController.php`, `app/Models/User.php`, `resources/js/Pages/Users/Index.vue`, `UserList.vue`; `routes/web.php` registers the management endpoint.
- `database/migrations/2026_10_12_131000_add_mobile_access_to_users.php` adds per-user mobile grants and challenge invalidation revision.
- `app/Models/Quotation.php` approval timestamp casts.
- `database/migrations/2026_10_09_180500_add_sales_mobile_quotation_approval.php`.
- `tests/Feature/SalesMobileApiTest.php`.
- `tools/build-sales-mobile-postman.cjs`, both Postman files and this guide.
- `docs/file-authorship.md` records comment-free artifact metadata.

Additional Swagger files: 
- tools/build-sales-mobile-openapi.cjs
- docs/openapi/sales-executive-mobile.openapi.json
- app/Http/Controllers/Api/SalesMobile/DocumentationController.php
- resources/views/sales-mobile-docs.blade.php

Mobile OTP cURL integration: app/Http/Controllers/Api/SalesMobile/AuthController.php and config/services.php. The former app/Services/Mobile/MobileOtpSender.php has been removed. Batches/Dispatch retains its existing services. All 24 mobile API tests pass.


## Maintaining mobile data visibility

The route group declares `MobileSalesScope` after `MobilePlantContext` once. It resolves the executive role and personnel identity for the request. `MobileAccess` owns the shared visibility queries and foreign-key validation; controllers use those helpers instead of repeating ownership conditions. Each helper returns a fresh query. Personnel identity is cached only on the request and is specific to the selected plant.

Use `Access::exists(Access::customers())` (or the corresponding resource query) for related-record validation. Keep plant-wide scheduling capacity and resource-conflict checks outside executive visibility filtering. Shared catalogs keep their existing plant validation. Lists, details, writes, lookups and summaries use the same ownership rules.
