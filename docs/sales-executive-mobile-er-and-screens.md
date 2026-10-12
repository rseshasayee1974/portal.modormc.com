<!--
Author: ragul-onemodo
Created: 2026-10-09 17:21:49 Asia/Calcutta (UTC+05:30)
-->

# Sales Executive App ER Diagrams and Screens

The mobile app uses the existing ERP records. These diagrams describe the logical model relationships used by the app; they do not imply every relationship is enforced by a database foreign key. Proposed mobile additions are shown separately and require design review and migrations before use.

## CRM and ownership

```mermaid
erDiagram
    mm_plants ||--o{ mm_crm_leads : scopes
    mm_users ||--o{ mm_crm_leads : assigned_to
    mm_users o|--o{ mm_personnels : user_id
    mm_users ||--o{ mm_crm_activities : assigned_to
    mm_crm_leads ||--o{ mm_crm_activities : lead_id
    mm_crm_leads ||--o{ mm_crm_attachments : lead_id
    mm_crm_leads ||--o{ mm_crm_deals : lead_id
    mm_patrons o|--o{ mm_crm_leads : converted_customer
    mm_patrons o|--o{ mm_crm_deals : customer_id
    mm_quotations o|--o{ mm_crm_deals : quotation_id
    mm_sales_orders o|--o{ mm_crm_deals : sales_order_id
    mm_crm_leads {
        bigint id PK
        bigint plant_id FK
        bigint assigned_to FK
        bigint customer_id FK "nullable until conversion"
        string lead_number
        string status
        string source
        string requirement "existing mix design name text"
        datetime converted_at
        datetime deleted_at
    }
    mm_crm_activities {
        bigint id PK
        bigint plant_id FK
        bigint lead_id FK
        bigint assigned_to FK
        string type
        string subject
        datetime due_at
        datetime completed_at
        datetime deleted_at
    }
    mm_crm_deals {
        bigint id PK
        bigint plant_id FK
        bigint lead_id FK
        bigint customer_id FK
        bigint quotation_id FK "optional"
        bigint sales_order_id FK "optional"
        string stage
        decimal expected_value
    }
```

Lead and activity owners are user IDs. Sales executive fields on commercial documents are personnel IDs. Resolve the user-to-personnel relationship before applying My Records filters. Although normal lead conversion creates one deal, the model is not shown as a database-enforced one-to-one relationship.

Existing activities belong to leads. Customer-only or order-only follow-ups need an explicit activity-model extension; the mobile API must not pretend those links already exist. The existing CRM requirement is a name string, not a mix_design_id foreign key.

## Quotations and sales orders

```mermaid
erDiagram
    mm_plants ||--o{ mm_quotations : scopes
    mm_patrons ||--o{ mm_quotations : patron_id
    mm_personnels o|--o{ mm_quotations : sales_executive_id
    mm_quotations ||--o{ mm_quotation_items : quotation_id
    mm_mix_designs o|--o{ mm_quotation_items : mix_design_id
    mm_taxes o|--o{ mm_quotation_items : tax_id
    mm_quotations o|--o{ mm_customer_pos : quotation_id
    mm_customer_pos o|--o{ mm_sales_orders : customer_po_id
    mm_patrons ||--o{ mm_sales_orders : customer_id
    mm_sites o|--o{ mm_sales_orders : site_id
    mm_mix_designs o|--o{ mm_sales_orders : mix_design_id
    mm_personnels o|--o{ mm_sales_orders : sales_executive_id
    mm_quotations {
        bigint id PK
        bigint plant_id FK
        bigint patron_id FK
        bigint site_id FK
        bigint sales_executive_id FK
        string prefix
        string reference
        date quote_date
        date validity_date
        boolean is_tax_inclusive
        decimal amount_total
        string status
        datetime deleted_at
    }
    mm_customer_pos {
        bigint id PK
        bigint plant_id FK
        bigint quotation_id FK
        bigint patron_id FK
        string reference
        string customer_po_reference
        date order_date
    }
    mm_sales_orders {
        bigint id PK
        bigint plant_id FK
        bigint customer_po_id FK
        bigint customer_id FK
        bigint site_id FK
        bigint mix_design_id FK
        bigint sales_executive_id FK
        string prefix
        int order_no
        decimal total_qty
        decimal produced_qty
        int status
        datetime deleted_at
    }
```

The commercial sequence is quotation → customer PO → sales order. Nullable links permit the ERP's existing alternative entry paths; the app should apply the existing validation rather than invent a mandatory relationship for every historical record.

## Batch schedules and delivery

```mermaid
erDiagram
    mm_sales_orders ||--o{ mm_concrete_batching_schedules : sales_order_id
    mm_sales_orders ||--o{ mm_batches : sales_order_id
    mm_sales_orders ||--o{ mm_dispatches : sales_order_id
    mm_batches o|--o{ mm_concrete_batching_schedules : batch_id
    mm_batches o|--o{ mm_dispatches : batch_id
    mm_dispatches o|--o{ mm_concrete_batching_schedules : dispatch_id
    mm_machines o|--o{ mm_concrete_batching_schedules : vehicle_id
    mm_personnels o|--o{ mm_concrete_batching_schedules : driver_id
    mm_mix_designs o|--o{ mm_concrete_batching_schedules : mix_design_id
    mm_plants ||--o{ mm_custom_settings : scopes
    mm_concrete_batching_schedules {
        bigint id PK
        bigint plant_id FK
        bigint sales_order_id FK
        bigint batch_id FK "nullable before batch creation"
        bigint dispatch_id FK "nullable before ticket creation"
        bigint vehicle_id FK
        bigint driver_id FK
        bigint mix_design_id FK
        string pour_reference
        date schedule_date
        decimal qty_m3
        string status
        datetime batching_time "nullable"
        datetime dispatch_time "nullable"
        datetime eta_site "nullable"
        datetime unloading_start "nullable"
        datetime unloading_end "nullable"
        datetime deleted_at
    }
    mm_batches {
        bigint id PK
        bigint plant_id FK
        bigint sales_order_id FK
        int batch_no
        decimal batch_size
        int status
        datetime deleted_at
    }
    mm_dispatches {
        bigint id PK
        bigint plant_id FK
        bigint sales_order_id FK
        bigint batch_id FK
        bigint truck_id FK
        bigint driver_id FK
        string prefix
        string dispatch_no
        string dispatch_status
        decimal delivered_qty
        datetime dispatch_time
        datetime delivery_time
        datetime deleted_at
    }
    mm_custom_settings {
        bigint id PK
        bigint plant_id FK
        string module_name
        json settings "batch_creation_source"
    }
```

The intended app workflow uses one selected schedule slot for a batch. The current schema has nullable batch_id and dispatch_id links without a unique constraint on each link, so the diagram shows possible stored cardinality rather than asserting one-to-one enforcement. Current creation validation prevents reusing a consumed slot.

Direct production selects a sales order; scheduled production selects a slot and uses that slot's sales_order_id in both batches and dispatches. Both use the same production logic. Batch numbers can be reused after soft deletion, so links use id, never batch_no as a foreign key.

## Proposed mobile additions

```mermaid
erDiagram
    mm_plants ||--o{ mobile_devices_proposed : scopes
    mm_users ||--o{ mobile_devices_proposed : owns
    mm_users ||--o{ mutation_receipts_proposed : submits
    mm_users ||--o{ field_visits_proposed : records
    mm_crm_leads o|--o{ field_visits_proposed : lead_id
    mm_patrons o|--o{ field_visits_proposed : customer_id
    marketing_campaigns_proposed o|--o{ campaign_leads_proposed : campaign_id
    mm_crm_leads ||--o{ campaign_leads_proposed : lead_id
    mobile_devices_proposed {
        bigint id PK
        bigint user_id FK
        bigint plant_id FK
        string installation_id
        string push_token
        string platform
        datetime revoked_at
    }
    mutation_receipts_proposed {
        bigint id PK
        bigint user_id FK
        bigint plant_id FK
        uuid request_id
        string operation
        string payload_hash
        bigint result_record_id
        datetime completed_at
    }
    field_visits_proposed {
        bigint id PK
        bigint plant_id FK
        bigint executive_user_id FK
        bigint lead_id FK "optional"
        bigint customer_id FK "optional"
        datetime visited_at
        string outcome
        text notes
    }
```

These table names are illustrative. Choose final names and indexes during API implementation. Scope idempotency uniqueness to user, plant and request_id; reject a retry with different payload contents. Campaign relationships are a later extension. Use the current Sanctum token storage and review existing notification facilities before adding overlapping tables.

## Mobile screen inventory

| Screen | Main content | Main actions |
|---|---|---|
| Login | User credentials, OTP if required, device state | Sign in, verify, sign out device |
| Plant selection | Authorized plants | Choose active plant |
| Dashboard | Follow-ups, lead pipeline, quotations, orders and production counts | Open filtered list, quick add |
| Lead list | Search, stage, owner, priority and next action | Add lead, open detail |
| Lead form | Contact, company, source, requirement, quantity, site and owner | Save draft, save changes |
| Lead detail | Contact, qualification, timeline, files and deal | Edit, add follow-up, qualify, convert once |
| Follow-up list | Today, overdue, upcoming and completed | Complete, reschedule, open related lead |
| Follow-up form | Type, due time, notes, owner and outcome | Save or complete |
| Marketing | Lead source performance and field visits | Add enquiry, record visit |
| Customer list | Search and assigned customers | Open customer |
| Customer detail | Contacts, sites, quotations, orders and production summary | Open order, call, add permitted activity |
| Quotation list | Reference, customer, amount and status | Add, open, filter |
| Quotation form | Customer/site, product or mix design, lines, sales tax and inclusive toggle | Save draft, update |
| Quotation detail | PDF, totals, status and downstream links | Preview, share, edit/delete when allowed |
| Customer PO | Accepted quotation/customer PO and line details | Convert through existing sales-order rules |
| Order list | Order, customer, quantity and progress | Open order, create if permitted |
| Order detail | Ordered, committed, dispatched and delivered quantities; distinct batch states | Open batches, dispatches and schedules |
| Batch schedules | Planned slots, site, mix, quantity, truck and linked batch | Create/edit planned slot where permitted |
| Schedule form | Sales order, date, logistics and optional times | Save with server availability validation |
| Batch detail | Batch number, status, quantity and linked dispatch | View operational progress |
| Notifications | Due actions, assignments and order updates | Open related record |
| More and profile | Customers, quotations, marketing, schedules and settings | Switch plant, view sync queue, sign out |

## Screen interaction rules

Use a fixed conceptual bottom navigation with Home, Leads, Follow Ups, Orders and More. On actual devices account for safe areas and keyboard behaviour. In the prototype the navigation stays in normal flow for preview accessibility.

Lead, quotation and schedule forms open as full mobile pages. Use a visible Back action, a clear Save action and field-level errors. Confirm unsaved changes when leaving a dirty form. Show whether data is a draft, pending synchronization or committed to the server.

Use optional null dates in schedule forms and display a dash for missing operational timestamps. Show batch and trip states separately. A read-only executive screen must not offer production weight or dispatch-status edits.

The interactive screen prototype uses fictional demo records. It demonstrates navigation and form layout; Save and Complete modify local demo state only. It does not call ERP APIs or create production records. Financial values are illustrative and final document calculations remain server-side.

## Design review checklist

- Confirm the phone layout, bottom navigation and placement of quotations/schedules under More.
- Confirm required lead fields and executive ownership visibility.
- Confirm which schedule and order actions executives may perform.
- Agree on labels for committed batches, completed production and delivered quantities.
- Review quotation forms with realistic long product/mix-design names.
- Test readable labels, keyboard operation, empty dates, loading, validation, no records and lost connectivity at small phone widths.

Read together with [the development plan](sales-executive-mobile-app.md). This document introduces no database migration or application API change.
