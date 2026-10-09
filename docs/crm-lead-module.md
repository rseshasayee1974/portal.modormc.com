<!--
Author: ragul-onemodo
Created: 2026-10-09 12:41:49 Asia/Calcutta (UTC+05:30)
-->

# CRM Leads

Open **CRM → Leads** at `/crm/leads` after applying the migration.

## Workflow

Create a lead with a phone or email, source, owner and commercial requirements.
Use List or Lead Pipeline to find and update leads. Open a lead for its overview,
activity timeline and attachments.
The list pencil action and the overview's Edit Lead button open a centered
editor with section navigation, a scrolling form, and fixed Save/Cancel actions.
Saving closes the editor and reloads the current lead details. Creation remains
in the top form.

Mix Design Requirement uses a searchable dropdown of active, nondeleted mix
designs from the current plant. Options contain label/value pairs and duplicate
names appear once. The selected name is saved in the existing requirement field. Previously saved text
remains selectable when it is no longer in the catalog. No schema change is needed.

Calls, meetings, site visits and
tasks require a due date; notes are recorded immediately. Complete scheduled
activities to clear them from overdue follow-ups. Overdue indicators are displayed
in the CRM dashboard and lead list.

Lead statuses: New → Contacted → Qualified → Converted, or Unqualified (reason
required). Only the Convert Lead action can set Converted. Select an existing,
active customer or leave the selector empty to create one. Conversion preserves
contacts/address and creates one customer-linked deal atomically. Duplicate patron
names or contacts require selecting an existing customer, or activating/updating
that patron first. Converted leads cannot be deleted or moved back to lead stages.

Deals have separate stages: Requirement Received → Quotation Sent → Negotiation →
Won / Lost. Lost requires a reason. Link existing quotations and sales orders from
the same customer and plant in the Deal tab. Creating quotations and sales orders
continues through the existing ERP modules.

Drag cards between stages in Lead Pipeline or Deals. Conversion and stages that
require reasons are completed through the lead detail forms.

The dashboard shows lead count, qualified leads, overdue activities, conversion
rate, open deal value, source counts and owner conversion counts. Bulk assignment
is available from the list. All records and lookups are scoped to the active plant.
Owner options include active users assigned to that plant/entity and the current
user. Attachments are stored on the private local disk; authenticated, authorized
downloads check both the lead and plant. Allowed files are documents/images up to
10 MB. Phone/email duplicates are blocked among nondeleted leads in the plant.

## Deployment

The existing ERP schema must be present before running this migration. Do not run
the full MenuSeeder on an existing installation: it truncates the menu table.
The CRM migration installs its menus and permissions directly.

```sh
php artisan migrate --path=database/migrations/2026_10_09_122748_create_crm_lead_module.php --force
npm run build
php artisan permission:cache-reset
php artisan view:clear
```

Deploy the complete generated `public/build` directory with its manifest alongside
the source changes. The migration adds four tables (`mm_crm_leads`,
`mm_crm_activities`, `mm_crm_deals`, `mm_crm_attachments`) and six permissions:
`CRM_LEAD.VIEW`, `.CREATE`, `.UPDATE`, `.DELETE`, `.ASSIGN`, `.CONVERT`.
System administrator roles and Sales Manager receive these permissions. Other
roles can be configured through the existing permission module. Seeders also
include CRM for fresh installations.

Email synchronization, outbound reminders, campaign integrations and automatic
quotation creation are outside this implementation. Follow-up reminders are shown
inside CRM.

## Source files

- `app/Http/Controllers/CrmLeadController.php`
- `app/Models/CrmLead.php`
- `app/Models/CrmActivity.php`
- `app/Models/CrmDeal.php`
- `app/Models/CrmAttachment.php`
- `app/Services/CrmLeadService.php`
- `app/Services/InstallCrmModule.php`
- `database/migrations/2026_10_09_122748_create_crm_lead_module.php`
- `database/seeders/MenuSeeder.php`
- `database/seeders/PermissionSeeder.php`
- `resources/js/Pages/CrmLeads/Index.vue`
- `resources/js/Pages/CrmLeads/LeadForm.vue`
- `resources/js/Pages/CrmLeads/LeadDetails.vue`
- `resources/js/Pages/CrmLeads/LeadEditModal.vue`
- `routes/web.php`
- `tests/Feature/CrmLeadTest.php`
- `.gitignore` (retains the new regression tests)
- `docs/crm-lead-module.md`

## Verification

`tests/Feature/CrmLeadTest.php` covers lifecycle, duplicate validation, role/action
permissions, plant isolation, bulk assignment, conversion/reuse/duplicate guards,
follow-up completion, dashboard calculations, customer-scoped deal links, private
attachments and idempotent permission/menu installation. It uses an isolated
SQLite database and does not change live business data.
