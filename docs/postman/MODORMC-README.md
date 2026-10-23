<!--
Author: Cipher
Created: 2026-10-23 10:41:47 Asia/Calcutta (UTC+05:30)
-->

# MODORMC Postman collection

Import `MODORMC.postman.json` and `MODORMC.postman_environment.json`, then select
**MODORMC - Admin and Sales Apps** as the active environment.

Set `base_url` to your server origin, such as `http://portal.modormc.com`, without
an `/api/mobile` or `/api/mobile/v1` suffix. Set `plant_id` to an accessible plant.
The source export in Downloads is preserved; these are the corrected import files.

## Folder layout

- **Admin App**: main-app login/OTP, profile, reporting, users, lead review, and
  quotation approval/rejection. These always use `/api/mobile` and `admin_token`.
- **Sales executive app**: sales login/OTP, profile, plants, leads, attendance,
  notifications, marketing summary, and customer detail. These always use
  `/api/mobile/v1` and `api_token`.
- **Root resource folders**: shared quotations, customer POs, sales orders,
  batch schedules, lookups, taxes, mix designs, sites, and customers. Shared
  requests appear only here, rather than being duplicated inside either app.
- **PO conversion and negative cases**: shared conversion and error examples.
  Use fresh disposable records and run these requests individually.

## Select the app for shared requests

Set `target_app` to `admin` or `sales` in the environment. A collection pre-request
script selects the matching URL prefix and login token for root requests.
`commercial_url` and `app_token` are derived local variables; do not set them.
App-specific folders always use their own prefix/token regardless of `target_app`.

For admin login, set `admin_email` and `admin_password` and run **Admin App >
Admin app authentication > login**. For sales login, set `email` and `password`
and run **Sales executive app > Authentication > Login**. Login response scripts
capture `admin_token` or `api_token`. OTP requests are alternatives to password
login; supply the received code using the corresponding OTP variable. Reviewer
login is an optional separate sales account and saves `reviewer_token`; root
sales requests continue using `api_token`.

## Test commercial requests

1. Log in, set `target_app`, and run the root lookup/customer/site/mix-design
   requests. Set `customer_id`, `site_id`, and `mix_design_id` from the selected
   plant responses. Quotation examples using taxes also need `sales_tax_id`;
   alternatively set the body `tax_id` to `null`. Enter `uom_id` where required.
2. Run **Customer PO > Create customer PO**, detail, update, and patch. Responses
   capture `customer_po_id` and `customer_po_item_id`. Item IDs change on update,
   so use the ID captured by the latest update before conversion.
3. Run a direct-order create example in **Sales orders**, followed by detail,
   update, batch history, and dispatch history. These capture/use `order_id`.
4. Use **Create disposable PO for deletion** before **Delete disposable PO**;
   it uses `delete_po_id` rather than the PO intended for conversion. Expected
   404 requests should be run after deleting their corresponding record.
5. With a fresh 10 m3 PO, run **PO conversion and negative cases** in order.
   Successful conversion allocates 4 m3 and captures `converted_order_id`.
   The direct-order remaining-quantity rejection is currently admin-only and
   automatically skips for `target_app=sales`. Other negative examples check
   invalid quantities, duplicate items, excess conversion, allocated PO changes,
   and missing-token rejection.

PO/order CRUD and conversion do not require module permissions. Token, plant,
ownership, validation, and lifecycle checks remain active. Admin lead/quotation
reviews have their own existing authorization rules. Quotation approval and
rejection are alternatives and require a submitted quotation. Do not run the
whole collection blindly: it contains destructive deletes, alternative examples,
and requests that deliberately expect 401, 404, or 422.

Date defaults are generated at request time. Set optional dates explicitly when
needed. Set `user_id`, `user_username`, `user_email`, and `user_mobile` only when
intentionally testing the user-update request. Record IDs from one app/user may
be unavailable when switching apps; create/select suitable records again.

## Rebuild

Run `node tools/build-modormc-postman.cjs "path/to/MODORMC.postman.json"` from the
repository root. The builder reads the supplied export and current generated
commercial examples, and writes the corrected collection and blank environment
under `docs/postman/`. It never overwrites the source export.
