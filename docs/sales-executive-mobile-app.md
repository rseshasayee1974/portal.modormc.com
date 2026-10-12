<!--
Author: ragul-onemodo
Created: 2026-10-09 17:19:00 Asia/Calcutta (UTC+05:30)
-->

# Sales Executive Mobile App Development Plan

The app should let field sales staff manage prospects, follow-ups, quotations and customer orders, and see production and delivery progress without contacting the plant for every update. Keep the current ERP as the source of truth and build mobile screens over authenticated APIs that share its business rules.

This is a proposed development specification. The mobile screens, API routes and new capabilities below are planned work, not a statement that they already exist.

## Recommended approach

Use a dedicated Vue mobile frontend with Capacitor and the existing Laravel backend. This fits the project's existing Vue skills and permits sharing validation conventions and presentation utilities. Design the screens for a phone rather than copying the desktop tables. Capacitor supports packaging web applications for Android and iOS with access to native features. See [Capacitor documentation](https://capacitorjs.com/docs).

Start with an Android pilot and a responsive browser version as a working assumption; confirm whether iOS is required before committing the release scope. Native packaging still requires platform builds, device testing and release preparation. Keep the mobile frontend separate from Inertia page routing and connect it to JSON APIs.

Use Laravel Sanctum for individually authenticated mobile devices, with revocable tokens and secure device storage. Browser authentication can use the appropriate cookie flow. See [Laravel Sanctum documentation](https://laravel.com/docs/12.x/sanctum). A shared plant API key is unsuitable as a sales executive identity.

## Existing project foundation

| Area | Existing foundation | Mobile development needed |
|---|---|---|
| CRM | Leads, activities, conversion, deals, attachments and permissions | JSON endpoints and phone screens with executive ownership filters |
| Quotations | Web CRUD, PDF download, email action and conversion status | Mobile CRUD, server calculation responses, revisions and sharing UI |
| Sales orders | Web CRUD and customer PO conversion | JSON endpoints, customer and order detail screens, progress summaries |
| Batch schedules | Schedule CRUD, batch and dispatch links, resource overlap validation | Mobile schedule selection, creation and authorized updates |
| Production | Batches and dispatches linked through sales_order_id | Order and customer summaries, trip timeline and refresh behaviour |
| Authentication | Existing login and Sanctum routes | Review and consolidate mobile login, token lifecycle and plant access |
| Dashboard | Existing authenticated summary endpoints | Executive-scoped metrics and a consistent mobile dashboard contract |

Existing web controllers often return Inertia responses. Put reusable calculations and mutations in shared services, then call those services from web and mobile controllers. Mobile and web clients must produce the same totals, statuses and validation results.

## Navigation and main screens

See [ER diagrams and mobile screens](sales-executive-mobile-er-and-screens.md) for the existing data relationships, proposed mobile additions and complete screen inventory.

Use five bottom navigation items: Home, Leads, Follow Ups, Orders and More. Provide a quick Add action for a lead, follow-up, visit or quotation. More contains Customers, Quotations, Batch Schedules, Marketing, Notifications and Profile.

Use cards with status badges, search and filters. Open a detail screen for editing rather than squeezing a desktop form into a small modal. Display current plant, synchronization state and the last refresh time where they affect decisions.

## Functional modules

| Module | Main screens and actions |
|---|---|
| Home dashboard | Today's follow-ups, overdue work, assigned leads, pipeline value, quotation progress, active orders, today's scheduled trips and batch progress |
| Marketing | Lead source and campaign tags, enquiries, site visits, customer meeting notes, attachments and results by source |
| Leads and CRM | List and search, create and edit, assigned owner, phone/email, customer/project, mix design requirement, quantity, expected value, stage, timeline and conversion |
| Follow ups | Today, Upcoming, Overdue and Completed views; call, meeting, site visit and task creation; reschedule; record outcome; complete and set next action |
| Customers | Contact and site list, click to call, quotations, orders, planned trips, completed batches and dispatch history |
| Quotations | Create, read, update and authorized soft delete; mix design/product lines; quantity, rate, discount and sales tax; tax-inclusive toggle; PDF preview and device sharing |
| Sales orders | List, details, authorized creation/editing, customer PO link, ordered quantity, rate, site, mix design and production/delivery progress |
| Batch schedules | Day/list view, sales order selection, planned quantity, mixer/driver, pump, manually selected dates and times, slot status and linked batch/dispatch |
| Notifications | Follow-up reminders, assignment changes, approval outcomes, schedule changes and relevant dispatch/completion updates |

Marketing in the first release means enquiry capture and visit activity. Campaign automation, bulk messaging and advertising integrations are separate later features. Opening a device share sheet or dialler is an explicit user action; background messaging should not be part of the first release.

### Leads and follow ups

Reuse the existing lead stages and conversion restrictions. Start with New, Contacted, Qualified, Converted and Unqualified; require the existing reason when marking a lead unqualified. Preserve the project's default note and activity behaviour.

Every follow-up should contain the related lead/customer, owner, activity type, due date/time, priority, notes and outcome. Completing a follow-up can offer a next follow-up, but must not silently create one. Keep overdue reminders separate from completed activity history.

### Quotations and sales orders

Follow the existing commercial sequence: lead qualification and customer creation, quotation, customer PO, then sales order. Do not introduce a mobile shortcut that bypasses required customer PO or ERP validation. Existing customer enquiries may start directly from a customer.

The server calculates taxes, discounts, tax-inclusive amounts, whole-rupee document totals and round-off using the current ERP rules. The app displays the result; it does not maintain a second accounting implementation. Fetch only sales taxes for quotations and sales documents.

Proposed quotation states are Draft, Submitted, Approved, Sent, Accepted, Rejected and Expired. Map these to existing fields or add an explicit migration after reviewing the current quotation model. Price exceptions and approval thresholds require business agreement before implementation.

Allow draft edits and permitted soft deletion. Once a quotation has a downstream customer PO or order, apply the ERP's existing restrictions and retain revision history. An executive should not delete an order that already has production, dispatch or invoicing activity.

### Batch schedules and production

Support the plant's existing batch_creation_source setting: Sales Order, Batch Schedule or Both. A selected schedule provides its sales_order_id to the established production flow.

Executives can request or edit a planned schedule where permitted. Dispatchers confirm vehicle and driver allocation. Mixer, driver and pump overlap checks remain server-side. The app must display a helpful conflict message and must not treat locally cached availability as authoritative.

Schedule Date starts empty and must be selected. Optional batching, dispatch, arrival and unloading date/time fields stay null unless selected in the form. Historical values remain visible when editing. Actual operational timestamps generated by production or dispatch actions are distinct from manual planning fields and should be labelled accordingly.

Show the schedule's linked batch number and dispatch ticket. Reflect actual batch progress: Planned → scheduled, Loading → batching, Dispatched → in_transit, Completed → completed, Cancelled → cancelled. Preserve later delivery stages such as on_site and pouring.

Only the existing Save Dispatch operation can move a batch to STATUS_DISPATCHED. Sales staff must not change production weights, material reconciliation or dispatch completion from this app by default. Invoice generation follows the current dispatched-state restriction.

## Order and customer batch progress

An order detail screen should show ordered quantity, batch counts by state, production quantity, dispatched quantity, delivered quantity, outstanding delivery quantity and upcoming schedule slots. A customer screen aggregates the same figures across that customer's accessible orders.

Example: an order for 40 m³ may have four batches totalling 24 m³, three dispatched trips totalling 18 m³ and two delivered trips totalling 12 m³. Show all three quantities separately. The remaining delivery quantity is 28 m³; it is not the same as the unproduced quantity of 16 m³.

| Metric | Definition |
|---|---|
| Batch count | Count distinct non-deleted mm_batches.id for the sales order, with cancelled batches shown separately |
| Completed batch count | Count batches explicitly in STATUS_COMPLETED; show STATUS_LOADING and STATUS_DISPATCHED separately |
| ERP produced quantity | Reuse the ERP definition: sum batch_size for non-deleted batches except cancelled; the current definition also includes planned batches |
| Dispatched quantity | Sum eligible dispatch quantities after the actual dispatch action; exclude Draft, Cancelled and deleted records |
| Delivered quantity | Sum quantities for dispatches with explicit delivery confirmation; an invoice alone is not proof of delivery |
| Remaining delivery quantity | Ordered quantity minus delivered quantity, with returns shown separately when supported |
| Customer progress | Aggregate accessible sales orders for that customer using the same definitions |

Label the current ERP produced metric as committed batch quantity if the business wants to avoid implying that planned batches are physically produced. Before changing the ERP definition, agree on a separate actual-production measure and its completion evidence.

Count batch IDs once even when joins return multiple dispatch, item or invoice rows. Exclude schedules and dispatches whose linked batch was deleted. Deleted batch numbers may be reused, so use immutable IDs to link records and deduplicate events rather than batch_no alone.

## Dashboard definitions

Default to My Records with Today, This Week, This Month and date-range filters. Managers can switch to My Team with an authorized owner filter.

Show new leads, qualified leads, conversion rate, open deal value, overdue follow-ups, quotations sent/accepted, active orders, order quantities, today's scheduled trips, loading/dispatched/completed batch counts and delivered volume. Each card opens the underlying filtered list so the number can be checked.

Define the conversion period and denominator explicitly. Keep quotation value, order value and invoiced revenue separate. If revenue or outstanding balances are shown, include posted credit/debit notes and use the existing financial ledger rules. Proposed campaign conversion metrics require campaign linkage first.

## Permissions and access

| Role | Suggested access |
|---|---|
| Sales executive | Own assigned leads, follow-ups, customers, quotations and orders; relevant schedule and production progress; allowed draft edits |
| Sales manager | Team records, reassignment, performance dashboard and approved commercial exceptions |
| Dispatcher or plant operator | Vehicle allocation, production progress and existing dispatch actions |
| Administrator | User/plant access, settings and permissions |

Enforce permissions on every API action and export. A hidden mobile button is not authorization. Validate record plant_id and the user's membership on the server. Return permitted plants at login and require a verified plant context per request; do not depend on a browser session for mobile token requests.

CRM assigned_to and sales_order.sales_executive_id may represent different identity types. Resolve the authenticated user to their personnel record and permitted team before applying ownership filters. Define which customers are shared with a team and which documents inherit access from an order.

## Proposed API contract

Create a versioned route group such as /api/mobile/v1. These are proposed endpoints, not existing route names.

| Resource | Proposed endpoints |
|---|---|
| Access | POST /auth/login, POST /auth/logout, GET /me, GET /plants |
| Dashboard | GET /dashboard with date and permitted owner/team filters |
| Lookups | GET /lookups for scoped customers, sites, mix designs, personnel, sales taxes and permitted settings |
| Leads | GET/POST /leads; GET/PATCH/DELETE /leads/{id}; POST /leads/{id}/convert |
| Follow ups | GET/POST /activities; PATCH /activities/{id}; POST /activities/{id}/complete |
| Customers | GET /customers; GET /customers/{id}; GET /customers/{id}/production-summary |
| Quotations | GET/POST /quotations; GET/PATCH/DELETE /quotations/{id}; GET /quotations/{id}/pdf |
| Customer PO | GET/POST /customer-pos; POST /customer-pos/{id}/convert-sales-order |
| Orders | GET/POST /sales-orders; GET/PATCH /sales-orders/{id}; GET /sales-orders/{id}/production-summary |
| Schedules | GET/POST /batch-schedules; GET/PATCH/DELETE /batch-schedules/{id} subject to current restrictions |
| Production | GET /sales-orders/{id}/batches; GET /sales-orders/{id}/dispatches |
| Device notifications | POST/DELETE /devices and GET/PATCH /notifications |

Use pagination, search, date filters, plant context and permitted ownership filters. Return validation errors by field. Return record versions or updated_at values for edit conflict checks, and a clear conflict response when the source changed. Store app dates consistently with entity timezone and show Asia/Calcutta for this deployment; distinguish dates from timestamps.

## Offline behaviour

Allow offline lead drafts, visit notes and follow-up drafts. Cache recently viewed customer/order summaries with a last-synced label. Show Pending Sync, Synced, Failed and Conflict states with an explicit retry action.

Use a client UUID and server idempotency record for each queued mutation. Repeated requests must not create duplicate leads, activities, conversions or documents. On reconnect, validate duplicates, permissions, plant access and record versions again. If the user loses access, discard unauthorized cached data and reject queued changes.

Require connectivity for final document numbering, quotation approval, lead conversion, order conversion and schedule allocation. Offline quotation drafts may use temporary local IDs; assign the official number only when saved successfully on the server. Stop automatic retries on a business conflict and let the user resolve it.

## Rollout prerequisites

The existing routes/api.php contains a legacy /getuserdetails route that returns user credential fields and uses a hard-coded shared token. Remove the credential-returning endpoint, review its access history and rotate the exposed shared credential before deploying a new mobile client. Do not incorporate that route into mobile login or profile APIs.

Consolidate the overlapping login paths, review token revocation and expiry, rate-limit authentication, and verify HTTPS deployment. Store tokens in native secure storage. Ensure attachments, PDFs and exports apply the same plant/ownership checks as record access. Notifications must not expose unnecessary customer or financial details on a locked device.

Prepare production queue workers, reminder scheduling, notification delivery, backups, application monitoring and device-release procedures. Define app support ownership and a rollback process for backend and mobile releases.

## Development phases

| Phase | Deliverables | Completion check |
|---|---|---|
| 1 Foundation and CRM | Access cleanup, shared services, versioned APIs, mobile shell, login, plants, leads, follow-ups, customer view and basic dashboard | Executive can complete enquiry and follow-up work with ownership and plant isolation verified |
| 2 Commercial workflow | Quotations CRUD/PDF, calculations, customer PO/order flow and draft restrictions | Mobile and web produce identical document amounts and cannot duplicate conversion |
| 3 Production visibility | Schedule CRUD, availability validation, order/customer batch summaries and dispatch timeline | Counts reconcile with ERP; Draft, deleted and cancelled records are handled correctly |
| 4 Field reliability | Offline drafts, synchronization, attachments, notifications, manager views and release testing | Interrupted requests, lost connectivity, permission changes and device logout are tested |

Release a complete online workflow first. Add offline mutation support after the shared services and duplicate protection are reliable. Estimate effort after approving screens, API coverage, approval rules and device scope rather than committing a fixed duration now.

## Acceptance tests

1. An executive cannot read or change another executive's private record or another plant's data by changing an ID or request header.
2. A lead can be created, followed up, qualified and converted once; duplicate phone/email validation matches the ERP.
3. Quotation totals match the ERP for discounts, sales tax, tax-inclusive pricing and rounding.
4. Customer PO and order conversion preserve customer, site, mix design, rates and ownership links.
5. Order and customer summaries reconcile with distinct batches and dispatches; deleted and cancelled records do not inflate active totals.
6. Schedule selection uses the correct sales_order_id, respects the plant setting and rejects real resource overlaps. Empty optional time fields stay null.
7. Only an authorized dispatch action sets STATUS_DISPATCHED; refreshing a Draft ticket does not reset a Loading schedule.
8. Offline retries do not duplicate records; concurrent edits produce a visible conflict rather than overwriting another user's changes.
9. Token revocation, logout and lost plant access remove cached access and block subsequent API calls.
10. A manager's dashboard totals match its filtered lists, with documented time periods and ownership definitions.

## Decisions to confirm before development

Confirm Android-only versus Android and iOS, private company distribution versus public stores, own-record versus team visibility, quotation approval thresholds, whether executives can create sales orders or only request them, and whether field visit GPS check-in is needed. GPS collection should be tied to an explicit visit action; continuous employee tracking is outside this proposal.

The first implementation milestone is the access review and shared API foundation, followed by Leads and Follow Ups. This gives executives useful daily work before introducing commercial approvals and production planning.
