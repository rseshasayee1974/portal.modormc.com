<!--
Author: Cipher
Created: 2026-10-24 16:48:55 Asia/Calcutta (UTC+05:30)
-->

# Mobile API endpoint audit

Audited the routes registered by `routes/mobileapp.php`, `routes/mobile-commercial.php`, and `routes/sales-mobile.php` on 2026-10-24. The registered inventory contains 129 route entries: 58 main-mobile entries and 71 sales-v1 entries. PUT/PATCH can share an entry; HEAD accompanies GET.

## Changes

- Routed admin quotation creation, deletion, and submission through the same admin controller used by listing, detail, and updates. Sales-v1 routes keep their creator-scoped controller.
- Required the matching admin quotation permission for create, update/submit, delete, and review. Non-administrator main-app users cannot invoke these actions.
- Returned JSON errors for mobile requests even when the client omits an Accept header or requests HTML. Authentication errors remain HTTP 401.
- Replaced model class names in uncaught record-not-found errors with resource-specific messages. Existing quotation update diagnostics and explicit order/PO/schedule messages remain available.
- Kept field validation errors, Retry-After and authentication headers, provider error codes, and support references through final error formatting.
- Unexpected exceptions and generic 5xx responses return an error_reference linked to server logs. Full exception details remain in logs; SQL, credentials, stack traces, and internal class names are not sent to clients. Expected accounting errors retain HTTP 422.
- Preserved specific WhatsApp TLS, configuration, connection, and submission errors and the unconfigured Reverb response.
- Rejected invalid page numbers consistently in paginated commercial endpoints.
- Blocked locked accounts at main-mobile login and plant-context entry. Added inactive/locked-account checks to authenticated legacy user/report endpoints.
- Returned HTTP 403 for inaccessible plant filters on legacy reporting instead of silently returning no results.

## Access-query review

| Family | Read/write access behavior |
| --- | --- |
| Admin quotations | Admin role and action permission; selected plant; draft/rejected records editable; pending/approved/converted records blocked |
| Sales quotations | Creator-scoped selected-plant query across reads and mutations; approval remains available in the admin app |
| Admin leads | Same overridden selected-plant query used by list/detail/update/status/approval |
| Sales leads and follow-ups | Creator-scoped lead query; follow-ups also check assignment and exclude system activities |
| Orders and production details | Access::orders() used by list/detail/update/delete/batches/dispatches |
| Customer POs and conversions | Access::customerPOs() used by reads and writes; quotation conversion uses the app-aware quotation query |
| Schedules | Access::schedules() limits both reads and changes to accessible orders; linked/active slots cannot be changed |
| Customers and sites | Shared selected-plant reference queries for list/detail; no master-data write endpoints |
| Attendance | Personnel- and plant-scoped service queries for read/check-in/check-out |
| Notifications | Same recipient/type/plant query for list/read/delivered; broadcast subscription checks the caller's own channel |
| Legacy reporting | Authenticated active account, validated filters, explicit plant access check; existing repository filtering retained |
| Users | Existing module permission and target-user access checks retained; inactive/locked callers blocked |

## Verification

- Dedicated audit/error-handling selection: **18 tests passed, 1,909 assertions**.
- Attendance: **26 tests passed, 187 assertions**.
- Final complete mobile API run: **141 tests; 101 passed, 39 assertion failures, one test error; 3,301 assertions**.
- All changed PHP files pass syntax checks; git diff whitespace checks pass.
- The route matrix tests enumerate all protected routes for missing/invalid tokens, all plant-context routes for missing/foreign plants, all controller action targets, and all parameterized routes for malformed IDs.
- Additional regressions cover admin actions on another creator's draft, permissions, soft deletion, cross-plant access, JSON without Accept, pagination, provider errors, support-reference logging, and transaction rollback after quotation/PO item-write failures.
- PHPUnit uses in-memory SQLite and mocked HTTP/notifications; no live customer records are changed and no real OTP is sent.

## Full-suite limitation

The full mobile API suite still has **39 assertion failures and one test error**. Every remaining failing test name also fails in an isolated snapshot of committed baseline `61c0c65ca`; the audit introduces no new failing test names. The baseline has **42 assertion failures and one test error**. Three previous failures are now resolved: the middleware-order matrix, the plant-header matrix, and sales-order missing-record messages.

These results do not certify every happy-path workflow. Existing failures include tests expecting older customer/site ownership restrictions, sales-app quotation approval, removed lookup response arrays, salesperson defaults, and lead fixtures that set assignment but omit the creator required by the current visibility query. Some expectations conflict with current explicit controller/access policies. They need reconciliation against the intended product contract before changing those policies or weakening tests.

Generated evidence is in `storage/logs/mobile-api-audit.xml`, `storage/logs/mobile-api-baseline.xml`, `storage/logs/mobile-api-regressions.xml`, and `storage/logs/mobile-api-routes.json` (ignored runtime artifacts).

## Remaining baseline failures

- `test_main_mobile_sales_executives_are_scoped_to_the_selected_plant`
- `test_customers_and_sites_are_shared_read_only_within_the_selected_plant`
- `test_sales_quotation_submission_admin_approval_and_status_reflection`
- `test_main_mobile_commercial_routes_require_their_own_token_and_plant`
- `test_create_quotation_from_lead_uses_source_details_and_shared_pricing`
- `test_lead_quotation_rejects_inaccessible_and_incomplete_source_data`
- `test_lead_quotation_requires_unambiguous_customer_site_and_allows_price_override`
- `test_lead_quotation_uses_logged_in_user_without_personnel_assignment`
- `test_lead_quotation_preserves_personnel_foreign_key_when_user_id_differs`
- `test_lead_quotation_accepts_global_uom_with_full_pricing_payload`
- `test_lead_quotation_automatically_creates_customer_contact_address_and_site`
- `test_lead_quotation_does_not_reuse_an_unrelated_same_name_site`
- `test_lead_quotation_ignores_deleted_and_foreign_same_name_sites`
- `test_converted_lead_quotation_rejects_an_unrelated_site_in_its_plant`
- `test_converted_lead_uses_related_site_instead_of_unrelated_same_name_site`
- `test_json_customer_sites_and_nested_po_orders_are_user_and_plant_scoped`
- `test_shared_commercial_routes_enforce_the_same_visibility_in_both_apps`
- `test_converted_lead_can_receive_partial_update_without_resending_status`
- `test_lead_status_dropdown_options_returned_by_api`
- `test_qualification_creates_customer_and_site_and_quotation_reuses_them`
- `test_qualification_reuses_customer_category_and_existing_site_by_name`
- `test_qualification_adds_customer_category_and_ignores_foreign_or_deleted_sites`
- `test_customer_provisioning_failure_rolls_back_lead_qualification`
- `test_qualification_rejects_inactive_existing_customer_without_duplicates`
- `test_workspace_endpoints_do_not_require_module_permissions_but_keep_plant_access`
- `test_lead_approval_requires_ownership_and_repeat_is_idempotent`
- `test_approved_lead_can_be_converted_and_configuration_resolves_personnel_users`
- `test_executive_visibility_is_consistent_across_lists_details_lookups_and_summaries`
- `test_followups_and_nested_lead_details_exclude_other_executives_assignments`
- `test_managers_and_administrators_are_also_restricted_to_their_own_mobile_records`
- `test_executive_writes_reject_inaccessible_foreign_keys_and_records`
- `test_direct_customer_assignment_allows_first_sale_and_controls_owner`
- `test_customer_and_site_access_inherits_only_visible_business_relationships`
- `test_missing_personnel_cannot_read_unassigned_sales_or_create_invisible_records`
- `test_legacy_public_auth_success_validation_inactive_and_otp_replay`
- `test_v1_missing_records_and_invalid_create_payloads`
- `test_direct_quotation_to_customer_po_conversion_in_both_mobile_apps`
- `test_quotation_status_and_approval_state_stay_in_sync`
- `test_tax_components_totals_and_separate_approval_user`
- `test_dropdowns_marketing_and_revoked_membership`

## Registered endpoint inventory

| Methods | URI | Controller action |
| --- | --- | --- |
| GET, HEAD | `api/mobile/batch-schedules` | `Api\SalesMobile\ScheduleController@listing` |
| POST | `api/mobile/batch-schedules` | `Api\SalesMobile\ScheduleController@store` |
| GET, HEAD | `api/mobile/batch-schedules/options` | `Api\SalesMobile\ScheduleController@options` |
| GET, HEAD | `api/mobile/batch-schedules/{schedule}` | `Api\SalesMobile\ScheduleController@detail` |
| PUT | `api/mobile/batch-schedules/{schedule}` | `Api\SalesMobile\ScheduleController@change` |
| DELETE | `api/mobile/batch-schedules/{schedule}` | `Api\SalesMobile\ScheduleController@remove` |
| GET, HEAD | `api/mobile/customer-details` | `Api\MobileApiController@customerDetails` |
| GET, HEAD | `api/mobile/customer-pos` | `Api\SalesMobile\CustomerPOController@index` |
| POST | `api/mobile/customer-pos` | `Api\SalesMobile\CustomerPOController@store` |
| GET, HEAD | `api/mobile/customer-pos/{customer_po}` | `Api\SalesMobile\CustomerPOController@show` |
| PUT, PATCH | `api/mobile/customer-pos/{customer_po}` | `Api\SalesMobile\CustomerPOController@update` |
| DELETE | `api/mobile/customer-pos/{customer_po}` | `Api\SalesMobile\CustomerPOController@destroy` |
| POST | `api/mobile/customer-pos/{customer_po}/convert-salesorder` | `Api\SalesMobile\CustomerPOController@convert` |
| GET, HEAD | `api/mobile/customers` | `Api\SalesMobile\WorkspaceController@customers` |
| GET, HEAD | `api/mobile/dashboard` | `Api\MobileApiController@dashboard` |
| GET, HEAD | `api/mobile/dispatch-batching-summary` | `Api\MobileApiController@dispatchBatchingSummary` |
| GET, HEAD | `api/mobile/dispatch-details` | `Api\MobileApiController@dispatchDetails` |
| GET, HEAD | `api/mobile/dropdowns` | `Api\SalesMobile\WorkspaceController@lookups` |
| GET, HEAD | `api/mobile/leads` | `Api\MobileAdminLeadController@index` |
| GET, HEAD | `api/mobile/leads/statuses` | `Api\MobileAdminLeadController@statuses` |
| GET, HEAD | `api/mobile/leads/{lead}` | `Api\MobileAdminLeadController@show` |
| PUT, PATCH | `api/mobile/leads/{lead}` | `Api\MobileAdminLeadController@update` |
| POST | `api/mobile/leads/{lead}/approve` | `Api\MobileAdminLeadController@approve` |
| GET, HEAD | `api/mobile/leads/{lead}/statuses` | `Api\MobileAdminLeadController@statuses` |
| POST | `api/mobile/login` | `Api\MobileApiController@login` |
| GET, HEAD | `api/mobile/mix-designs` | `Api\SalesMobile\WorkspaceController@mixDesigns` |
| GET, HEAD | `api/mobile/orders` | `Api\SalesMobile\OrderController@index` |
| POST | `api/mobile/orders` | `Api\SalesMobile\OrderController@store` |
| GET, HEAD | `api/mobile/orders/{order}` | `Api\SalesMobile\OrderController@show` |
| PUT, PATCH | `api/mobile/orders/{order}` | `Api\SalesMobile\OrderController@update` |
| DELETE | `api/mobile/orders/{order}` | `Api\SalesMobile\OrderController@destroy` |
| GET, HEAD | `api/mobile/orders/{order}/batches` | `Api\SalesMobile\OrderController@batches` |
| GET, HEAD | `api/mobile/orders/{order}/dispatches` | `Api\SalesMobile\OrderController@dispatches` |
| GET, HEAD | `api/mobile/quotations` | `Api\MobileAdminQuotationController@index` |
| POST | `api/mobile/quotations` | `Api\MobileAdminQuotationController@store` |
| GET, HEAD | `api/mobile/quotations/{quotation}` | `Api\MobileAdminQuotationController@show` |
| PUT, PATCH | `api/mobile/quotations/{quotation}` | `Api\MobileAdminQuotationController@update` |
| DELETE | `api/mobile/quotations/{quotation}` | `Api\MobileAdminQuotationController@destroy` |
| POST | `api/mobile/quotations/{quotation}/convert-customer-po` | `Api\SalesMobile\CustomerPOController@convertQuotation` |
| POST | `api/mobile/quotations/{quotation}/review` | `Api\MobileAdminQuotationController@review` |
| POST | `api/mobile/quotations/{quotation}/submit` | `Api\MobileAdminQuotationController@submit` |
| GET, HEAD | `api/mobile/sales-details` | `Api\MobileApiController@salesDetails` |
| GET, HEAD | `api/mobile/sales-executives` | `Api\SalesMobile\WorkspaceController@salesExecutives` |
| GET, HEAD | `api/mobile/sales-summary` | `Api\MobileApiController@salesSummary` |
| POST | `api/mobile/send-otp` | `Api\MobileApiController@sendOtp` |
| GET, HEAD | `api/mobile/sites` | `Api\SalesMobile\WorkspaceController@sites` |
| GET, HEAD | `api/mobile/status` | `Api\MobileApiController@status` |
| GET, HEAD | `api/mobile/stock-details` | `Api\MobileApiController@stockDetails` |
| GET, HEAD | `api/mobile/taxes` | `Api\SalesMobile\WorkspaceController@taxes` |
| GET, HEAD | `api/mobile/top-mix-designs` | `Api\MobileApiController@topMixDesigns` |
| GET, HEAD | `api/mobile/truck-dispatch-details` | `Api\MobileApiController@dispatchDetailsByTruck` |
| GET, HEAD | `api/mobile/user` | `Api\MobileApiController@user` |
| GET, HEAD | `api/mobile/users` | `UserController@index` |
| POST | `api/mobile/users` | `UserController@store` |
| GET, HEAD | `api/mobile/users/{user}` | `UserController@show` |
| PUT, PATCH | `api/mobile/users/{user}` | `UserController@update` |
| DELETE | `api/mobile/users/{user}` | `UserController@destroy` |
| GET, HEAD | `api/mobile/v1/attendances` | `Api\SalesMobile\AttendanceController@index` |
| POST | `api/mobile/v1/attendances/check-in` | `Api\SalesMobile\AttendanceController@checkIn` |
| GET, HEAD | `api/mobile/v1/attendances/current` | `Api\SalesMobile\AttendanceController@current` |
| GET, HEAD | `api/mobile/v1/attendances/{attendance}` | `Api\SalesMobile\AttendanceController@show` |
| POST | `api/mobile/v1/attendances/{attendance}/check-out` | `Api\SalesMobile\AttendanceController@checkOut` |
| POST | `api/mobile/v1/auth/login` | `Api\SalesMobile\AuthController@login` |
| POST | `api/mobile/v1/auth/logout` | `Api\SalesMobile\AuthController@logout` |
| POST | `api/mobile/v1/auth/send-otp` | `Api\SalesMobile\AuthController@sendOtp` |
| POST | `api/mobile/v1/auth/verify-otp` | `Api\SalesMobile\AuthController@verify` |
| GET, HEAD | `api/mobile/v1/batch-schedules` | `Api\SalesMobile\ScheduleController@listing` |
| POST | `api/mobile/v1/batch-schedules` | `Api\SalesMobile\ScheduleController@store` |
| GET, HEAD | `api/mobile/v1/batch-schedules/options` | `Api\SalesMobile\ScheduleController@options` |
| GET, HEAD | `api/mobile/v1/batch-schedules/{schedule}` | `Api\SalesMobile\ScheduleController@detail` |
| PUT | `api/mobile/v1/batch-schedules/{schedule}` | `Api\SalesMobile\ScheduleController@change` |
| DELETE | `api/mobile/v1/batch-schedules/{schedule}` | `Api\SalesMobile\ScheduleController@remove` |
| GET, HEAD | `api/mobile/v1/customer-pos` | `Api\SalesMobile\CustomerPOController@index` |
| POST | `api/mobile/v1/customer-pos` | `Api\SalesMobile\CustomerPOController@store` |
| GET, HEAD | `api/mobile/v1/customer-pos/{customer_po}` | `Api\SalesMobile\CustomerPOController@show` |
| PUT, PATCH | `api/mobile/v1/customer-pos/{customer_po}` | `Api\SalesMobile\CustomerPOController@update` |
| DELETE | `api/mobile/v1/customer-pos/{customer_po}` | `Api\SalesMobile\CustomerPOController@destroy` |
| POST | `api/mobile/v1/customer-pos/{customer_po}/convert-salesorder` | `Api\SalesMobile\CustomerPOController@convert` |
| GET, HEAD | `api/mobile/v1/customers` | `Api\SalesMobile\WorkspaceController@customers` |
| GET, HEAD | `api/mobile/v1/customers/{customer}` | `Api\SalesMobile\WorkspaceController@customer` |
| GET, HEAD | `api/mobile/v1/dashboard` | `Api\SalesMobile\WorkspaceController@dashboard` |
| GET, HEAD | `api/mobile/v1/docs` | `Api\SalesMobile\DocumentationController@index` |
| GET, HEAD | `api/mobile/v1/follow-ups` | `Api\SalesMobile\LeadController@followups` |
| POST | `api/mobile/v1/follow-ups` | `Api\SalesMobile\LeadController@saveActivity` |
| PUT | `api/mobile/v1/follow-ups/{activity}` | `Api\SalesMobile\LeadController@saveActivity` |
| DELETE | `api/mobile/v1/follow-ups/{activity}` | `Api\SalesMobile\LeadController@deleteActivity` |
| POST | `api/mobile/v1/follow-ups/{activity}/complete` | `Api\SalesMobile\LeadController@complete` |
| GET, HEAD | `api/mobile/v1/leads` | `Api\SalesMobile\LeadController@index` |
| POST | `api/mobile/v1/leads` | `Api\SalesMobile\LeadController@store` |
| GET, HEAD | `api/mobile/v1/leads/statuses` | `Api\SalesMobile\LeadController@statuses` |
| GET, HEAD | `api/mobile/v1/leads/{lead}` | `Api\SalesMobile\LeadController@show` |
| PUT, PATCH | `api/mobile/v1/leads/{lead}` | `Api\SalesMobile\LeadController@update` |
| DELETE | `api/mobile/v1/leads/{lead}` | `Api\SalesMobile\LeadController@destroy` |
| POST | `api/mobile/v1/leads/{lead}/approve` | `Api\SalesMobile\LeadController@approve` |
| POST | `api/mobile/v1/leads/{lead}/convert` | `Api\SalesMobile\LeadController@convert` |
| POST | `api/mobile/v1/leads/{lead}/quotation` | `Api\SalesMobile\QuotationController@createFromLead` |
| GET, HEAD | `api/mobile/v1/leads/{lead}/statuses` | `Api\SalesMobile\LeadController@statuses` |
| GET, HEAD | `api/mobile/v1/lookups` | `Api\SalesMobile\WorkspaceController@lookups` |
| GET, HEAD | `api/mobile/v1/marketing/summary` | `Api\SalesMobile\WorkspaceController@marketing` |
| GET, HEAD | `api/mobile/v1/me` | `Api\SalesMobile\AuthController@me` |
| GET, HEAD | `api/mobile/v1/mix-designs` | `Api\SalesMobile\WorkspaceController@mixDesigns` |
| GET, HEAD | `api/mobile/v1/notifications` | `Api\SalesMobile\NotificationController@index` |
| POST | `api/mobile/v1/notifications/broadcasting/auth` | `Api\SalesMobile\NotificationController@broadcastAuth` |
| GET, HEAD | `api/mobile/v1/notifications/connection` | `Api\SalesMobile\NotificationController@connection` |
| POST | `api/mobile/v1/notifications/read-all` | `Api\SalesMobile\NotificationController@readAll` |
| GET, HEAD | `api/mobile/v1/notifications/unread-count` | `Api\SalesMobile\NotificationController@unreadCount` |
| POST | `api/mobile/v1/notifications/{notification}/delivered` | `Api\SalesMobile\NotificationController@delivered` |
| POST | `api/mobile/v1/notifications/{notification}/read` | `Api\SalesMobile\NotificationController@read` |
| GET, HEAD | `api/mobile/v1/openapi.json` | `Api\SalesMobile\DocumentationController@specification` |
| GET, HEAD | `api/mobile/v1/orders` | `Api\SalesMobile\OrderController@index` |
| POST | `api/mobile/v1/orders` | `Api\SalesMobile\OrderController@store` |
| GET, HEAD | `api/mobile/v1/orders/{order}` | `Api\SalesMobile\OrderController@show` |
| PUT, PATCH | `api/mobile/v1/orders/{order}` | `Api\SalesMobile\OrderController@update` |
| DELETE | `api/mobile/v1/orders/{order}` | `Api\SalesMobile\OrderController@destroy` |
| GET, HEAD | `api/mobile/v1/orders/{order}/batches` | `Api\SalesMobile\OrderController@batches` |
| GET, HEAD | `api/mobile/v1/orders/{order}/dispatches` | `Api\SalesMobile\OrderController@dispatches` |
| GET, HEAD | `api/mobile/v1/plants` | `Api\SalesMobile\AuthController@plants` |
| GET, HEAD | `api/mobile/v1/quotations` | `Api\SalesMobile\QuotationController@index` |
| POST | `api/mobile/v1/quotations` | `Api\SalesMobile\QuotationController@store` |
| GET, HEAD | `api/mobile/v1/quotations/{quotation}` | `Api\SalesMobile\QuotationController@show` |
| PUT, PATCH | `api/mobile/v1/quotations/{quotation}` | `Api\SalesMobile\QuotationController@update` |
| DELETE | `api/mobile/v1/quotations/{quotation}` | `Api\SalesMobile\QuotationController@destroy` |
| POST | `api/mobile/v1/quotations/{quotation}/convert-customer-po` | `Api\SalesMobile\CustomerPOController@convertQuotation` |
| POST | `api/mobile/v1/quotations/{quotation}/review` | `Api\SalesMobile\QuotationController@review` |
| POST | `api/mobile/v1/quotations/{quotation}/submit` | `Api\SalesMobile\QuotationController@submit` |
| GET, HEAD | `api/mobile/v1/sites` | `Api\SalesMobile\WorkspaceController@sites` |
| GET, HEAD | `api/mobile/v1/sites/{site}` | `Api\SalesMobile\WorkspaceController@site` |
| GET, HEAD | `api/mobile/v1/taxes` | `Api\SalesMobile\WorkspaceController@taxes` |
| POST | `api/mobile/verify-otp` | `Api\MobileApiController@verifyOtp` |
