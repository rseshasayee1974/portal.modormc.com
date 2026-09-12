# Discounts

The Finance → Discounts screen is available at `/finance/discounts`. The create form appears above the searchable table. Expand a row to inspect or edit it. Delete requires confirmation.

## Storage and references

`mm_account_discount` follows the supplied column names, decimal precision, enums, and audit fields. Eloquent writes `modified_at` instead of `updated_at`. Plant and audit user IDs come from the current session, not submitted form data.

- `journal_id` selects an existing `mm_journal_entries` voucher.
- `account_id` optionally selects an `mm_ledgers` account, following the portal's journal-line convention.
- `partner_id` selects an `mm_patrons` partner.
- `move_id` is retained as an optional integer reference. No target table was specified in the supplied schema.
- Fixed discounts copy `value` into `amount` on the server. Percentage discounts store a rate from 0.01 to 100 and a separately entered positive amount. No calculation base was supplied in the schema.
- The module maintains discount records and links to existing journals. It does not post journal entries or change invoice balances.
- Deletion removes the discount row; linked journal, ledger, and partner records remain.

## Installation

Run the dedicated migration on an installation where this table has not yet been created:

```sh
php artisan migrate --path=database/migrations/2026_09_11_170000_create_account_discount_table.php
php artisan db:seed --class=DiscountModuleSeeder
npm run build
```

The additive seeder installs `DISCOUNT.VIEW`, `CREATE`, `UPDATE`, and `DELETE`, grants them to the existing administrative and finance roles, and registers the Finance menu entry. It is also called by `MenuSeeder`; the permissions are included in `PermissionSeeder` for subsequent full reseeds.

## Validation

`tests/Feature/AccountDiscountTest.php` covers CRUD, related-record lookup, audit stamps, money validation, enum casing through request middleware, active-plant enforcement, cross-plant rejection, and permission denial. The tests use an isolated in-memory database.
