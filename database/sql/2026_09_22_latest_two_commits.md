# SQL update for the latest two commits

Import `2026_09_22_latest_two_commits.sql` into the existing application database.
The file starts with `USE v4_modomines1`, matching the local application configuration.
Change that database name before importing if the target server uses a different name.

Reviewed commits:

- `0f5377246` — opening balances, batch permissions and report enhancements.
- `e0ba983b7` — merge commit with no additional database changes compared with either parent.

The SQL covers these changes:

| Object | Change |
| --- | --- |
| `mm_opening_balance_audit_logs` | Create the audit-log table with snapshot JSON fields and indexes. |
| `mm_opening_balance_batches` | Add `patron_id`, `account_id`, `active_key`, `replaces_id`, `deleted_at` and `deleted_by` when missing. Replace the unique plant constraint with a unique `(plant_id, active_key)` constraint. |
| `mm_opening_balance_batches` | Create the complete base table if the earlier opening-balance setup has not been installed. |
| `mm_permissions` | Add/update `OPENING_BALANCE.VIEW`, `CREATE`, `UPDATE`, `DELETE` and `AUDIT_LOG`. |
| `mm_role_has_permissions` | Grant the five permissions to existing administrator roles. Other grants are preserved. |
| `mm_menus` | Use `OPENING_BALANCE.VIEW` for the menu. If missing, create it under the Journal Entries menu's parent. |
| `migrations` | Register the completed opening-balance migrations to prevent Artisan from repeating their DDL. |

The manual batch-number permission check, locked financial-year date, Vue form split,
report changes and Redis removal do not require additional schema changes.

## Import

1. Back up the target database and place the app in maintenance mode while applying DDL.
2. Verify the `USE` statement names the correct application database, then import the SQL file in phpMyAdmin or run it using the MySQL client. The first result should show that database as `selected_application_database`.
3. Run the complete file in order. Stop on an error and resolve it before running the migration-registration section. DDL commits automatically.
4. Check the verification results at the end: two opening-balance tables, six added columns, the `opening_balance_active_target` unique index, five permissions, and one opening-balance menu entry.
5. Run these commands in the deployed application folder:

   ```sh
   php artisan config:clear
   php artisan permission:cache-reset
   php artisan cache:forget global_roles_version
   ```

6. Reload the application and assign opening-balance permissions to any non-admin roles that need them.

Core application tables (`mm_permissions`, `mm_roles`, `mm_role_has_permissions`,
`mm_menus`, and `migrations`) must already exist. Other earlier migrations are outside
this script's scope. Legacy balances remain available for conversion through the app.
Existing table engines and recorded accounting values are preserved. Newly created
tables use InnoDB. This script does not generate historical audit events.

The SQL guards missing columns and known indexes and can be rerun after a successful
import. It does not repair arbitrary incompatible schemas. Do not run migration
registration separately from the successful schema and permission changes. If the
menu verification is empty, restore the Journal Entries menu and rerun the file.

Validated on local MySQL 9.1 using two isolated scratch databases: missing base table
and legacy base table. Both initial import and rerun passed, with 26 checks covering
schema, uniqueness, data preservation, permissions, menu and migration records.
The application database was only read during preparation and verification.

## Unknown table in information_schema

An error such as `Unknown table 'mm_opening_balance_batches' in information_schema`
means an application-table query is resolving against the metadata database.
`information_schema` contains database metadata, not the application's balance tables.
Run the complete updated script, including its `USE` statement, in the same import.
When running individual statements, select the application database first:

```sql
USE `v4_modomines1`; -- Replace with the target application's database name if different.
SELECT DATABASE();
SHOW TABLES LIKE 'mm_opening_balance_batches';
```

If the table is absent in the correct database, run the full script from the beginning
so its `CREATE TABLE IF NOT EXISTS` statements run before the alterations.
