# SaaS API Documentation

## Authentication

- `POST /api/auth/register`
  - Body: `name`, `email`, `password`, `plan` (`free|paid`)
- `POST /api/auth/login`
  - Body: `email`, `password`
- `POST /api/auth/regenerate-key`
  - Header: `Authorization: Bearer <api_key>`

Role rules:

- Only users with role `saas owner` or `platform admin` can generate/regenerate billing API keys.

## Modules

All module endpoints require:

- Header: `Authorization: Bearer <api_key>`
- Rate limit: `60 requests/minute`
- Body: `input` (string), `entity_id` (required), `plant_id` (optional)

Endpoints:

- `POST /api/chat`
- `POST /api/image`
- `POST /api/search`

Response includes:

- `tokens_used`
- `pricing`
- `usage_alert` (true when usage reaches 80% of plan limit)

## Dashboard

- `GET /api/dashboard`
- Header: `Authorization: Bearer <api_key>`
- Query: `entity_id` (required), `plant_id` (optional)

Returns:

- `total_tokens`
- `total_cost`
- `total_requests`
- `module_breakdown`
- `daily_usage`

## Billing

- `POST /api/billing/generate` (optional `month=YYYY-MM`)
- `GET /api/billing/history?entity_id=1&plant_id=2`
- `POST /api/billing/{billing}/pay` with `{ "success": true|false, "entity_id": 1, "plant_id": 2 }`

All billing endpoints are scoped by:

- `user_id`
- `entity_id`
- `plant_id` (nullable)

Access rule:

- Only users with role `saas owner` or `platform admin` can access billing endpoints.

## Frontend

- `/saas/dashboard` for usage dashboard
- `/saas/billing` for billing history and mock payment actions

Frontend pages automatically pass active context from:

- `session('active_entity_id')`
- `session('active_plant_id')`

Usage alert visibility:

- `DashboardApiController` returns `usage_alert` only for `saas owner` / `platform admin`; for other roles it returns `null`.

Plan + limits source:

- Token limits and alert threshold are resolved from existing subscription tables:
  - `mm_entity_subscriptions`
  - `mm_plans`
  - `mm_plan_features`
  - `mm_features`
  - `mm_subscription_statuses`
- Expected feature codes in `mm_features.code`:
  - `token_limit` or `monthly_token_limit`
  - `usage_alert_threshold`

