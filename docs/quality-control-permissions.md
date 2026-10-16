<!--
Author: Cipher
Created: 2026-10-16 14:59:38 Asia/Calcutta (UTC+05:30)
-->

# Quality control permissions and multi-age scheduling

The canonical permissions are installed by `database/seeders/PermissionSeeder.php`.
QC controllers use the same names as the menu and role configuration. Assign them
through the existing role/permission screen; this change does not automatically
grant additional access to existing users. The standard application administrator
bypasses in `AuthorizesModule` remain in effect.

## Permissions enforced by the QC controllers

| Permission | Allowed operation |
| --- | --- |
| `QC.VIEW` | View the quality dashboard. |
| `QC_SAMPLE.VIEW` | List samples and production sources. |
| `QC_SAMPLE.CREATE` | Create samples. Scheduling their tests also requires `QC_TEST.CREATE`. |
| `QC_SAMPLE.UPDATE` | Open the sample editor and update sample metadata. |
| `QC_SAMPLE.DELETE` | Delete a sample. |
| `QC_TEST.VIEW` | List/filter tests and view their measurements/results. |
| `QC_TEST.CREATE` | Schedule one or more test ages on an existing sample, or with a new sample. |
| `QC_TEST.UPDATE` | Enter/evaluate results and mark an unapproved test for retest. |
| `QC_TEST.DELETE` | Delete an unapproved test while retaining its sample. |
| `QC_TEST.APPROVE` | Approve an evaluated pass/fail test. Pending tests cannot be approved. |
| `QC_TEST.PDF` | Download/print the concrete test certificate or worksheet. |
| `QC_CONFIG.VIEW` | View test types, parameters, units, material mappings, schedules and grade configurations. |
| `QC_CONFIG.CREATE` | Open creation forms and create configuration records. |
| `QC_CONFIG.UPDATE` | Open editors, update configuration, toggle units/types, or save material mappings. |
| `QC_CONFIG.DELETE` | Delete configuration records. |
| `QC_REPORT.VIEW` | View the QC report listing. |
| `QC_REPORT.PDF` | Download the QC summary PDF. |

Record-bound operations reject records from another plant with HTTP 404. Shared
configuration records with a null plant remain available under the existing shared
configuration policy. Missing permissions return HTTP 403 (unauthenticated access
is handled by authentication middleware).

## Additional permissions present in the permission catalog

`QC.CREATE`, `QC.UPDATE`, `QC.DELETE`, `QC.EXPORT`, `QC.APPROVE`,
`QC_TEST.EXPORT` and `QC_REPORT.EXPORT` are also seeded. The QC controllers currently
have no separate endpoint for these generic actions/exports. They do not substitute
for the specific permissions in the table above. Client-side table exports use data
already obtained under the corresponding VIEW permission.

The separate Inventory concrete-quality-test module uses
`CONCRETE_QUALITY_TEST.VIEW`, `.CREATE`, `.UPDATE`, `.DELETE`, `.EXPORT` and `.PDF`.
Its invoice lookup now explicitly checks VIEW. PDF/export catalog entries are not a
replacement for the laboratory-test permissions.

## Multiple test ages

The sample form supports any nonempty selection of **7, 15 and 28 days**. It submits
numeric milestones rather than labels. Each age creates its own test with a unique
number, calculated targets, and dispatch-date-based scheduled date. Sample editing
continues to edit metadata; additional ages are scheduled through the creation flow
using the existing sample. Metadata updates reject invalid milestone arrays and any
attempt to replace the existing schedule.

New sample: `POST /quality/samples` (other sample/source fields still apply):

```json
{
  "sample_date": "2026-10-16",
  "test_type_ids": [1],
  "schedule_milestones": [7, 15, 28]
}
```

Existing sample: `POST /quality/tests`:

```json
{
  "sample_id": 1,
  "test_type_id": 1,
  "schedule_milestones": [7, 15, 28],
  "remarks": "Scheduled cube tests"
}
```

The existing-sample endpoint still accepts the legacy scalar `age_days` instead of
`schedule_milestones`. Sending both is invalid. Successful requests redirect to the
earliest selected test's results editor. These are existing web/Inertia endpoints,
not new sales-mobile API routes.

For a new sample, omitting `schedule_milestones` retains the supported configured
milestones, falling back to 28 days. An explicitly empty selection is invalid.
The controller ignores unsupported ages in legacy test-type parameter configuration.

## Invalid requests and consistency

Send `Accept: application/json` to obtain Laravel's HTTP 422 JSON validation errors
(`message` and `errors`) rather than a form redirect. The UI shows the same errors
beside the age selector, including errors on `schedule_milestones.0` and later items.

Rejected inputs include empty/non-array selections, unsupported ages, repeated ages,
more than three ages, duplicate test-type IDs, a repeated existing sample/type/age,
conflicting old/new age fields, inactive/deleted/foreign-plant test types, foreign
sample/source identifiers, unknown technicians, orphan source keys, and contradictory
direct dispatch/batch/customer IDs. Cancelled production cannot be scheduled.

Multi-age creation is transactional. The existing sample is locked while validating
duplicates and creating tests. A duplicate or invalid selection rejects the entire
request and leaves samples/tests unchanged. Existing tests are never overwritten or
silently skipped. Approval requires APPROVE permission and evaluated results; approved
tests cannot be edited, deleted or marked for retest.

The due list continues to show only pending/retest milestones matching exactly 7,
15 or 28 days since dispatch; scheduling several ages does not show future milestones
as due today.
