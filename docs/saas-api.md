# SaaS API Documentation

Base URL for local testing:
`http://127.0.0.1:8000/api`

## Authentication

### `POST /api/auth/login`

Supports both email login and mobile OTP login.

Email login request:
```json
{
  "email": "demo@modomines.com",
  "password": "password"
}
```

Mobile OTP send request:
```json
{
  "mobile": "6767676767"
}
```

Mobile OTP verify login request:
```json
{
  "mobile": "6767676767",
  "otp": "123456"
}
```

Success response:
```json
{
  "status": true,
  "message": "Login Success",
  "token_type": "Bearer",
  "access_token": "token",
  "token": "token",
  "user": {
    "id": 1,
    "name": "Demo User",
    "email": "demo@modomines.com",
    "mobile": "6767676767",
    "default_entity_id": 1,
    "default_plant_id": 2,
    "profile_photo_url": null
  }
}
```

OTP send response:
```json
{
  "status": true,
  "message": "OTP sent successfully",
  "login_type": "mobile_otp",
  "otp_required": true,
  "expires_in": 300,
  "mobile": "6767676767",
  "debug_otp": "123456"
}
```

### `POST /api/auth/logout`

Headers:
- `Authorization: Bearer YOUR_TOKEN`
- `Accept: application/json`

Response:
```json
{
  "status": true,
  "message": "Logged out successfully"
}
```

### Legacy auth routes

These routes are also present in `routes/api.php`:
- `POST /api/login`
- `POST /api/send-otp`
- `POST /api/verify-otp`
- `POST /api/resend-verification-email`

## Dashboard APIs

All dashboard APIs require:
- `Authorization: Bearer YOUR_TOKEN`
- `Accept: application/json`

Common query parameters:
- `plant_id`
- `type`
- `from_date`
- `to_date`

Date filter example:
`?from_date=2026-05-01&to_date=2026-05-18&plant_id=2&type=daily`

### `GET /api/dashboard/sales-summary`

Purpose:
- total sales
- cash sales
- credit sales
- % split

Example response:
```json
{
  "status": true,
  "data": {
    "total_sales": 4000,
    "cash_sales": {
      "amount": 1500,
      "%": 37.5
    },
    "credit_sales": {
      "amount": 2500,
      "%": 62.5
    }
  }
}
```

### `GET /api/dashboard/sales-details`

Purpose:
- sales details by payment mode
- amount plus quantity in `cbm`, `cft`, `mtr`

Example response:
```json
{
  "status": true,
  "data": {
    "cash_sales": {
      "sales_amount": {
        "qty": 1500,
        "unit": "amount"
      },
      "quantity": {
        "cbm": {
          "qty": 3.75,
          "unit": "cbm"
        },
        "cft": {
          "qty": 132.43,
          "unit": "cft"
        },
        "mtr": {
          "qty": 15.75,
          "unit": "mtr"
        }
      }
    },
    "credit_sales": {
      "sales_amount": {
        "qty": 2500,
        "unit": "amount"
      },
      "quantity": {
        "cbm": {
          "qty": 4.0,
          "unit": "cbm"
        },
        "cft": {
          "qty": 141.259,
          "unit": "cft"
        },
        "mtr": {
          "qty": 20.75,
          "unit": "mtr"
        }
      }
    }
  }
}
```

### `GET /api/dashboard/dispatch-batching-summary`

Purpose:
- total dispatch batch size in `cbm`
- total dispatch net quantity in `mtr`
- total dispatch net quantity in `cft`
- total batching count

Example response:
```json
{
  "status": true,
  "data": {
    "total_dispatch_batch_size": {
      "qty": 5.5,
      "unit": "cbm"
    },
    "total_dispatch_net_quantity": {
      "mtr": {
        "qty": 25.0,
        "unit": "mtr"
      },
      "cft": {
        "qty": 194.231,
        "unit": "cft"
      }
    },
    "total_batching_count": {
      "qty": 2,
      "unit": "count"
    }
  }
}
```

### `GET /api/dashboard/dispatch-details`

Purpose:
- truck wise dispatch details
- total dispatch count
- total batch size
- total quantity in `cft` and `mtr`

Example response:
```json
{
  "status": true,
  "data": [
    {
      "truck_id": 12,
      "truck_registration": "TN 09 CH 8476",
      "total_dispatch_count": {
        "qty": 2,
        "unit": "count"
      },
      "total_batch_size": {
        "qty": 2,
        "unit": "cbm"
      },
      "total_qty": {
        "cft": {
          "qty": 70.629,
          "unit": "cft"
        },
        "mtr": {
          "qty": 2,
          "unit": "mtr"
        }
      }
    }
  ]
}
```

### `GET /api/dashboard/top-mix-designs`

Purpose:
- top 5 selling mix designs
- grade from `mm_concrete_grades.name`
- design name from `mm_mix_designs.design_name`

Example response:
```json
{
  "status": true,
  "data": [
    {
      "mix_design_id": 1,
      "design_name": "M40 Premium",
      "design_code": "M40P",
      "grade": "M40",
      "total_batch_size": {
        "qty": 7.5,
        "unit": "cbm"
      },
      "total_batch_count": {
        "qty": 2,
        "unit": "count"
      }
    }
  ]
}
```

### `GET /api/dashboard/stock-details`

Purpose:
- current stock from `mm_products` and `mm_quantity`

Example response:
```json
{
  "status": true,
  "data": [
    {
      "product_id": 11,
      "product_name": "Cement",
      "product_code": "CEM001",
      "current_stock": {
        "qty": 60.5,
        "unit": "KG"
      },
      "stock_alert": {
        "qty": 50,
        "unit": "KG"
      },
      "stock_status": {
        "in_stock": true,
        "below_alert": false
      }
    }
  ]
}
```

### `GET /api/dashboard/customer-details`

Purpose:
- group by customer and grade
- return customer name, grade, mix design
- material consumption from `mm_batch_materials`
- data source based on `mm_dispatches`

Example response:
```json
{
  "status": true,
  "data": [
    {
      "customer_id": 1,
      "customer_name": "ABC Builders",
      "grade": "M30",
      "mix_design": {
        "id": 10,
        "design_name": "M30 Pump Mix"
      },
      "total_dispatch_count": {
        "qty": 2,
        "unit": "count"
      },
      "material_consumption": [
        {
          "material_name": "Cement",
          "target_qty": {
            "qty": 610,
            "unit": "kg"
          },
          "actual_qty": {
            "qty": 617.5,
            "unit": "kg"
          },
          "deviation_qty": {
            "qty": 7.5,
            "unit": "kg"
          }
        }
      ]
    }
  ]
}
```

### Additional dashboard controller methods present

These methods exist in `DashboardController`, but the routes are currently commented in `routes/api.php`:
- `GET /api/dashboard/sales-stats`
- `GET /api/dashboard/top-products`
- `GET /api/dashboard/dispatch-sales-amount`
- `GET /api/dashboard/trips-details`
- `GET /api/dashboard/alerts`

### `GET /api/master/plants`

Purpose:
- plant dropdown / filter list

Example response:
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "plant_name": "Main Plant"
    }
  ]
}
```

## Module APIs

These APIs require API key middleware:
- `Authorization: Bearer YOUR_API_KEY`
- rate limit `60/minute`

Routes:
- `POST /api/chat`
- `POST /api/image`
- `POST /api/search`

Common request fields:
```json
{
  "input": "your prompt",
  "entity_id": 1,
  "plant_id": 2
}
```

Expected response fields:
- `tokens_used`
- `pricing`
- `usage_alert`

## Production APIs

Routes:
- `POST /api/production__Order__data`
- `POST /api/production/batch`

These routes are inside the `api.key` middleware group.

### Mock batch endpoints

For local testing:
- `GET /api/batch`
- `GET /api/test-batch`

These return mock batching payloads for integration testing.

## Billing APIs

These routes are inside the `api.key` middleware group:
- `POST /api/billing/generate`
- `POST /api/billing/history`
- `POST /api/billing/{billing}/pay`

Access rules:
- intended for `saas owner` and `platform admin`

## Frontend reference

Frontend pages:
- `/saas/dashboard`
- `/saas/billing`

Active context source:
- `session('active_entity_id')`
- `session('active_plant_id')`

## Notes

- Dashboard APIs use Sanctum bearer token authentication.
- Module, production, and billing APIs use API key middleware.
- For `type=daily`, pass both `from_date` and `to_date`.
- Local OTP testing may expose `debug_otp` only in local environment.
