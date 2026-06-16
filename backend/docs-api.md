# API Overview

All authenticated endpoints require:

```text
Authorization: Bearer <token>
```

## Public

- POST `/api/register`
- POST `/api/login`

## Player

- GET `/api/me`
- GET `/api/crimes`
- POST `/api/crimes/{id}/commit`
- GET `/api/crime-logs`
- GET `/api/weapons`
- POST `/api/weapons/{id}/buy`
- GET `/api/inventory`
- GET `/api/drug-market`
- GET `/api/gangs`
- POST `/api/gangs`
- GET `/api/territories`

## Admin

- GET `/api/admin/dashboard`
- GET `/api/admin/audit`
