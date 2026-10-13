<!--
Author: ragul-onemodo
Created: 2026-10-13 11:03:28 Asia/Calcutta (UTC+05:30)
-->

# Modo ERP MCP

The authenticated HTTP MCP server exposes CRM leads, follow-ups, reports and sales-order batch progress. It uses the official `laravel/mcp` package (a production dependency) and existing application/domain services.

## Connect

- Local endpoint: `http://127.0.0.1:8000/mcp/erp`
- Production endpoint: `https://YOUR_ERP_HOST/mcp/erp`
- Transport: HTTP MCP, JSON-RPC 2.0 via POST.
- Required headers: `Authorization: Bearer TOKEN`, `X-Plant-Id: PLANT_ID`, `Content-Type: application/json`, `Accept: application/json`.
- Supported protocol versions are negotiated during initialization; use `2025-11-25` with the installed package.
- This release uses manually configured Sanctum bearer tokens. Use an MCP client that supports custom authorization and plant headers. OAuth discovery/consent for clients that require OAuth is not implemented.
- The sales mobile login tokens are not accepted here. ERP MCP tokens have only the `erp-mcp` ability and do not grant mobile app access or modify `is_otp_enabled`.

A trusted server administrator issues a token against an existing user:

```sh
php artisan mcp:erp-token executive@example.com --days=30
```

The token is shown once. Keep it in the MCP client's secret configuration; do not put it in URLs, frontend bundles, source control or logs. Tokens expire after 1 to 30 days. Multiple clients can use separate tokens and choose different plants the user already has access to.

Revoke that user's ERP MCP tokens, leaving mobile and website tokens alone:

```sh
php artisan mcp:erp-token executive@example.com --revoke
```

The command does not create users, memberships, roles or module permissions.

### Initialize

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-11-25",
    "capabilities": {},
    "clientInfo": {"name": "modo-assistant", "version": "1.0.0"}
  }
}
```

Send `notifications/initialized` next, then `tools/list` or `tools/call`. Preserve a returned `MCP-Session-Id` if the client uses sessions. `MCP-Protocol-Version: 2025-11-25` may be supplied on subsequent requests.

### Discover tools

```json
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}
```

Only tools permitted for the authenticated user and selected plant are listed. Report tools appear only when at least one supported report is permitted.

### Call a tool

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"sales_order_batch_progress","arguments":{"order_id":14}}}
```

Tool responses contain JSON text in `result.content[].text`. Validation, denied access or inaccessible records return a tool error (`result.isError: true`) with a safe message. Invalid tool names return JSON-RPC errors. Authentication/context errors can return HTTP 401/403/422 before MCP handling. No SQL statements, exception traces or credentials are returned by tool handlers.

## Tools and permissions

| Tool | Required permission | Purpose |
|---|---|---|
| `list_crm_leads` | `CRM_LEAD.VIEW` | Search/status filtering and pagination |
| `get_crm_lead` | `CRM_LEAD.VIEW` | Lead details and activities |
| `create_crm_lead` | `CRM_LEAD.CREATE` | Create using the existing validation/duplicate rules |
| `update_crm_lead` | `CRM_LEAD.UPDATE` | Patch supplied fields while preserving the others |
| `list_follow_ups` | `CRM_LEAD.VIEW` | `today`, `upcoming`, `overdue`, `completed`, `all` |
| `create_follow_up` | `CRM_LEAD.UPDATE` | Create Call/Visit/Meeting/Note according to current allowed activity types |
| `update_follow_up` | `CRM_LEAD.UPDATE` | Patch an incomplete activity |
| `complete_follow_up` | `CRM_LEAD.UPDATE` | Mark completed; repeated completion is safe |
| `list_sales_orders` | `SALES_ORDER.VIEW` | Search, customer filter and pagination |
| `sales_order_batch_progress` | `SALES_ORDER.VIEW` | Ordered/committed/dispatched/delivered/remaining volume and batch counts |
| `list_sales_order_batches` | `SALES_ORDER.VIEW` | Paginated batches for one order |
| `list_reports` | At least one supported `REPORT_*.VIEW` | Accessible report names |
| `run_report` | The selected report's `REPORT_*.VIEW` | Read report rows and totals |

Existing singular/plural module permission aliases are respected. Assignment to another lead owner additionally requires `CRM_LEAD.ASSIGN`; the target owner must belong to the selected plant. Regular users see their assigned leads and orders they created or own as sales executive. Existing `CRM_LEAD.VIEW_ALL` and `SALES_ORDER.VIEW_ALL` grants broaden record access inside the selected plant; system administrators retain the existing application's access rules.

No delete, lead conversion, finance posting, payment mutation or arbitrary SQL tools are exposed. Configure client approval for the four create/update tools and follow-up completion. Server instructions request review before those writes; the MCP client controls its approval UI.

### Lead and follow-up examples

```json
{"name":"create_crm_lead","arguments":{"contact_name":"Arun","phone":"9876543210","enquiry_date":"2026-10-13","source":"Referral","priority":"High","status":"New","expected_value":100000}}
```

```json
{"name":"update_crm_lead","arguments":{"lead_id":12,"project_name":"New factory"}}
```

```json
{"name":"create_follow_up","arguments":{"lead_id":12,"type":"Call","subject":"Confirm concrete requirement","due_at":"2026-10-14 10:00:00"}}
```

```json
{"name":"complete_follow_up","arguments":{"activity_id":24,"description":"Customer confirmed requirement."}}
```

The examples are the `params` object of a `tools/call` request. Schema discovery gives all supported arguments. Dates use `YYYY-MM-DD`; follow-up datetimes use the selected entity's timezone. `System` activities cannot be created through MCP.

## Reports

Supported types:

- `sales_register` (summary)
- `purchase_register` (summary)
- `customer_outstanding`
- `sales` (Sales & Dispatches)
- `batching_schedule`
- `production_batch`
- `inventory_stock`
- `patron`

Each type uses the existing report service and its specific view permission. Report access is independent of lead/order ownership: granting a report view permission gives the same plant-level report scope as the existing ERP report UI.

```json
{"name":"run_report","arguments":{"report":"sales_register","start_date":"2026-10-01","end_date":"2026-10-13","page":1,"per_page":20}}
```

`start_date` and `end_date` are required; the range is limited to 366 days. Optional `patron_id` must belong to the selected plant and not be soft-deleted. Top-level row collections are paginated; totals cover the complete report. Register pagination is performed by the existing streaming report service. Nested collections are capped at 100 items and omitted tails are identified by `truncated_paths`. Use narrower dates/patron filters for detailed investigation. `per_page` must be 1 to 100; default 20.

## Access isolation

- A valid, unexpired `erp-mcp` personal access token is required on every request; browser sessions and mobile device tokens are insufficient.
- Inactive, locked and deleted users are rejected.
- `X-Plant-Id` is mandatory. The plant must be active and accessible through the user's entity/plant memberships, using the existing system-admin rules.
- Permissions come from the selected entity/plant role plus direct grants. An unrelated global role cannot override the selected contextual role.
- Tools cannot accept `plant_id`, `user_id`, `export` or unknown arguments. Context comes exclusively from authenticated middleware.
- Record queries retain soft-delete, plant and ownership filtering. Out-of-scope record IDs return a safe not-found error.
- A temporary session supplies context to legacy domain services and is restored after the request. Browser workspace choices and user defaults are not modified.
- Calls are limited to 60 per minute per authenticated user. Domain model audits remain active. `erp_mcp_call` log entries contain user ID, plant ID, tool name and result only, without tokens or request contents.
- Requests with an `Origin` header must match `APP_URL` or a configured `MCP_ALLOWED_ORIGINS` entry. Server-to-server clients normally omit Origin.

Optional environment configuration (comma-separated complete origins):

```dotenv
MCP_ALLOWED_ORIGINS=https://your-erp.example.com
```

## Deploy

Deploy the changed source files, then:

```sh
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

No MCP database migration or frontend build is required. Existing CRM tables, Sanctum tokens and module/report permissions must already be installed. MCP does not run pending CRM migrations automatically. Use HTTPS in production and configure a token-aware MCP client with the correct ERP host and plant ID.

## Validation

36 feature tests / 269 assertions pass, including the inherited mobile API regression suite and 10 MCP-specific tests. Coverage includes MCP initialization/discovery, per-tool filtering, dedicated token checks, disabled/locked accounts, token revocation, origin rejection, plant and ownership boundaries, lead/follow-up writes, preserved fields, idempotent completion, report permission enforcement, pagination and context restoration. All eight supported report services were additionally checked against local MySQL through the gateway. These checks did not issue production tokens or run financial mutations.

## Changed files

- `composer.json`, `composer.lock`: production MCP dependency.
- `routes/ai.php`: authenticated MCP registration.
- `config/erp_mcp.php`: optional allowed origins.
- `app/Http/Middleware/McpPlantContext.php`: user/token/plant context isolation.
- `app/Mcp/Servers/ErpServer.php`: ERP server and instructions.
- `app/Mcp/ErpToolCatalog.php`: tools, arguments and discovery permissions.
- `app/Mcp/Tools/ErpTool.php`: schemas, annotations and safe responses.
- `app/Mcp/ErpToolGateway.php`: existing domain/controller/report adapter.
- `app/Console/Commands/ErpMcpToken.php`: trusted administrator token management.
- `.gitignore`: include the MCP test and shared sales-mobile test fixture in version control.
- `tests/Feature/ErpMcpTest.php`: MCP integration and access tests.
- `tests/Feature/SalesMobileApiTest.php`: existing isolated fixture/regression suite now included for the MCP test dependency.
- `docs/erp-mcp.md`: this connection and deployment guide.
