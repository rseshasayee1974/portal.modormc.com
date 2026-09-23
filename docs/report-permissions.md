# Individual report permissions

Open **Roles > Edit > Privilege Matrix**. Each of the 34 reports has a separate row, for example **Report: Sales Register**, **Report: Detailed Sales Register** and **Report: Purchase Register**.

Assign **View** to open that report. **Export** allows its Excel/PDF downloads (including bulk ZIP where supported), **Share** allows public links, and **Schedule** allows recurring report setup and cancellation. Each action also requires that report's View permission. Existing system-administrator access is retained.

Sales Register Summary and Detailed Sales Register use separate permissions. Purchase Register Summary and Detail share the Purchase Register permission. The Deleted Report is protected, including its `deleted` alias. QC reports outside the main Report catalog retain their existing `QC_REPORT` permissions.

Permission names follow `REPORT_<TYPE>.<ACTION>`, such as `REPORT_SALES_REGISTER.VIEW` and `REPORT_DETAILED_SALES_REGISTER.EXPORT`. Report menu entries, the category/report selector and export controls use the same access rules as the endpoints. A user assigned only a report without a direct submenu can still open the Report menu and select it.

The active entity/plant role takes precedence over global role grants; direct user grants supplement it. Export status and new download links require the requesting user, matching active plant and current report Export permission. New generated files are stored in `storage/app/private/reports`. Previously generated files in public storage are not relocated by this migration. Revoking Share invalidates that creator's public report links. Scheduled runs check the creator's current Schedule permission; schedules without a valid creator are skipped.

## Install

```powershell
php artisan migrate --path=database/migrations/2026_09_23_140000_add_individual_report_permissions.php --force
npm.cmd run build
php artisan queue:restart
```

The additive migration creates 136 permissions. Existing `REPORT.VIEW` grants become each report's View grant; `REPORT.EXPORT` grants become Export and Share grants. Existing report Create/Delete/Schedule grants become Schedule grants. Grants are copied for both roles and directly assigned users; unrelated permissions are retained. Re-running the installer does not re-grant revoked permissions. The old Report blanket row is hidden from the role matrix and no longer grants access at runtime. Adjust the individual report rows to restrict a role.

The migration was applied to the local database on 23 September 2026. Full permission seeding also defines the new permissions, and menu seeding preserves their links.

## Verification

```powershell
php tests/Standalone/report-permissions.php
php tests/Standalone/register-reports.php
php tests/Standalone/report-export-queue.php
```

Permission tests use isolated SQLite fixtures. They check every report/action, separate summary/detail access, denied direct URLs and exports, legacy role/direct grant migration, idempotence, tenant and plant scope, private downloads, schedule visibility, public-link revocation and rollback. No messages are sent by these tests.
