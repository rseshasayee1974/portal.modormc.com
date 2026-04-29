# Modomines ERP - Permissions Matrix

Below is the definitive list of granular permissions systematically mapped onto the `spatie/laravel-permission` architecture to securely guard the application. 
Every module requires explicit checking allowing independent assignment of reading, mutating, updating or visual elements such as mapping inside a Navigation pane (`menu`).

## Available Module Scope

The architecture operates via a dot-notation convention: `[module].[action]`

### Defined Modules:
* users
* roles
* permissions
* entities
* address_types
* entity_types
* contact_types
* bank_account_types
* countries
* currencies
* invoice_statuses
* plans
* state_codes
* subscription_statuses
* machines
* personnel

### Defined Actions Per Module:
* **menu**: Grants the user visibility to the specific object type link inside the `MasterNav` / `UserNav` sidebars or top bars.
* **show**: Defines read-only access to view a specific record.
* **create**: Grants privileges to instantiate new structures or fill out creation modals.
* **edit**: Grants privileges to open identical modification tabs for entities.
* **update**: Permits physical database storage overriding payloads via `PUT/PATCH`. (Separated from `edit` for nuanced API-layer separation if desired).
* **delete**: Grants destructive privileges to permanently remove / pseudo-remove entities.

## Complete Dictionary Mapping
(A total of roughly 84 permissions dynamically parsed to generate the frontend React/Vue role assignment `<n-checkbox>` matrix grid)

* users.menu
* users.show
* users.create
* users.edit
* users.update
* users.delete

* roles.menu
* roles.show
* roles.create
* roles.edit
* roles.update
* roles.delete

* permissions.menu
* permissions.show
* permissions.create
* permissions.edit
* permissions.update
* permissions.delete

* entities.menu
* entities.show
* entities.create
* entities.edit
* entities.update
* entities.delete

* address_types.menu
* address_types.show
* address_types.create
* address_types.edit
* address_types.update
* address_types.delete

* entity_types.menu
* entity_types.show
* entity_types.create
* entity_types.edit
* entity_types.update
* entity_types.delete

* contact_types.menu
* contact_types.show
* contact_types.create
* contact_types.edit
* contact_types.update
* contact_types.delete

* bank_account_types.menu
* bank_account_types.show
* bank_account_types.create
* bank_account_types.edit
* bank_account_types.update
* bank_account_types.delete

* countries.menu
* countries.show
* countries.create
* countries.edit
* countries.update
* countries.delete

* currencies.menu
* currencies.show
* currencies.create
* currencies.edit
* currencies.update
* currencies.delete

* invoice_statuses.menu
* invoice_statuses.show
* invoice_statuses.create
* invoice_statuses.edit
* invoice_statuses.update
* invoice_statuses.delete

* plans.menu
* plans.show
* plans.create
* plans.edit
* plans.update
* plans.delete

* state_codes.menu
* state_codes.show
* state_codes.create
* state_codes.edit
* state_codes.update
* state_codes.delete

* subscription_statuses.menu
* subscription_statuses.show
* subscription_statuses.create
* subscription_statuses.edit
* subscription_statuses.update
* subscription_statuses.delete
