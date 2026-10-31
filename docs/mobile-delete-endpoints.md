<!--
Author: Cipher
Created: 2026-10-30 18:04:16 Asia/Calcutta (UTC+05:30)
-->

# Mobile DELETE endpoints

All endpoints use HTTP `DELETE`. Put the record ID in the URL. **No request body or query parameters are required**; in Postman select Body â†’ none.

## Headers

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
X-Plant-Id: 1
```

`X-Plant-Id` is required for the commercial endpoints and follow-ups. The selected plant must be accessible to the authenticated user. `/api/mobile/users/{user}` uses the existing user-management authorization/context rather than the mobile commercial plant middleware.

Sales app (`/api/mobile/v1`): use a valid sales-mobile token. Main app (`/api/mobile`): use a token accepted by the main mobile API. IDs below are URL placeholders, not test database IDs to use on a live database.

## Complete route inventory

| DELETE URL | Required URL parameter | Body |
| --- | --- | --- |
| `/api/mobile/leads/{lead}` | Lead ID | None |
| `/api/mobile/v1/leads/{lead}` | Lead ID | None |
| `/api/mobile/quotations/{quotation}` | Quotation ID | None |
| `/api/mobile/v1/quotations/{quotation}` | Quotation ID | None |
| `/api/mobile/customer-pos/{customer_po}` | Customer PO ID | None |
| `/api/mobile/v1/customer-pos/{customer_po}` | Customer PO ID | None |
| `/api/mobile/orders/{order}` | Sales order ID | None |
| `/api/mobile/v1/orders/{order}` | Sales order ID | None |
| `/api/mobile/batch-schedules/{schedule}` | Batch schedule ID | None |
| `/api/mobile/v1/batch-schedules/{schedule}` | Batch schedule ID | None |
| `/api/mobile/v1/follow-ups/{activity}` | CRM activity ID, not its lead ID | None |
| `/api/mobile/users/{user}` | User ID | None |

The five shared resource routes are registered in both apps by `mobile-commercial.php` and the lead resources. There is no main-app follow-up DELETE route or sales-app user DELETE route in these files. IDs for commercial resources and activities must be numeric.

## Deletion rules and effects

| Resource | Rules and effects |
| --- | --- |
| Lead | Requires `CRM_LEAD.DELETE`. Converted leads cannot be deleted. Sales app restricts records to their creator; main app requires an administrator role in the selected plant. Soft deletes the lead. |
| Quotation | Draft or rejected quotations can be deleted. Pending approval, approved, or PO-linked quotations cannot be deleted. Sales app restricts records to their creator. Main app requires administrator access and quotation DELETE/VIEW permissions. Soft deletes quotation items too. |
| Customer PO | Completed POs, POs with sales orders, or POs linked to dispatches cannot be deleted. Soft deletes PO items; clears the linked quotation's `is_customer_po` flag when no active PO remains. |
| Sales order | Completed or cancelled orders, or orders with batches, dispatches, or batch schedules, cannot be deleted. Soft deletes the order. |
| Batch schedule | Only `scheduled` slots without a batch or dispatch link can be deleted. Scope follows access to the parent sales order. Soft deletes the schedule; refreshes relevant pour/order balances. A missing pour reference is supported. |
| Follow-up | Requires `CRM_LEAD.DELETE` and access to both the parent lead and the activity assignment. System activities cannot be deleted through this endpoint. Soft deletes the activity. |
| User | Requires existing user DELETE authorization, role hierarchy and target-user access. Soft deletes the user and entity memberships; removes the profile photo if present. |

Customer POs and orders are restricted to the sales executive in the sales app; authorized commercial administrators in the main app can access records within the selected plant. Mobile quotations, POs, orders and schedules in the sales app do not require extra module DELETE permissions; authentication, plant and record visibility, and lifecycle guards still apply.

## Example

Replace `YOUR_RECORD_ID` with an accessible, deletable record ID:

```bash
curl --request DELETE 'https://YOUR_HOST/api/mobile/v1/batch-schedules/YOUR_RECORD_ID' \
  --header 'Authorization: Bearer YOUR_TOKEN' \
  --header 'X-Plant-Id: 1' \
  --header 'Accept: application/json'
```

No `--data` argument is needed. Use the appropriate URL from the inventory for other resources.

Successful requests return HTTP **200**:

```json
{
  "success": true,
  "message": "Lead deleted."
}
```

The message varies by resource. Repeating a successful deletion returns **404**, since the record is soft deleted.

| Status | Meaning |
| --- | --- |
| 200 | Deleted successfully |
| 401 | Missing or invalid authentication |
| 403 | Missing permission, administrator role, plant membership, or target-user access |
| 404 | Missing/deleted record, malformed commercial ID, or record outside the endpoint's visibility |
| 422 | Missing/invalid plant header or a lifecycle rule blocks deletion |

Authorization checks may run before record lookup; an unauthorized caller can receive 403 even for a missing record.

## Automated verification

The DELETE tests in `tests/Feature/SalesMobileApiTest.php` use an isolated in-memory SQLite database. They do not delete existing MySQL records.

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'test_mobile_delete_|test_mobile_user_management_valid_crud|test_customer_po_dispatch_guard_uses_sales_orders' tests/Feature/SalesMobileApiTest.php
```

Coverage includes an exact 12-route inventory, successful deletion without payloads, repeat requests, child-item and membership soft deletion, deletion audit fields, quotation unlinking, missing/malformed IDs, authentication, plant isolation, creator/executive/activity ownership, administrator permissions, and business lifecycle guards.

Verified result: **11 tests passed, 246 assertions**.
