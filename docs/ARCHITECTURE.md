# Architecture

## Backend

The backend is a lightweight PHP 8 REST API with MVC-style structure:

```text
backend/app/Core        framework core: router, database, request, response
backend/app/Controllers HTTP/API controllers
backend/app/Services    business logic
backend/app/Models      simple model table wrappers
backend/database        SQL migrations and seeders
backend/routes/api.php  API route registration
```

All database interaction uses PDO prepared statements. Authentication uses bearer tokens stored as SHA-256 hashes in `api_tokens`.

## Frontend

React + Vite single-page app:

```text
frontend/src/api        API client
frontend/src/main.tsx   app/pages/components
frontend/src/styles     styling
```

## Security Included

- Password hashing with Argon2id
- Prepared SQL statements
- Token auth with hashed token storage
- CORS config
- Server-authoritative game calculations
- Audit logs

## Future Production Hardening

- Add rate limiting middleware
- Add CSRF for cookie-based web sessions
- Add refresh tokens
- Add queue/scheduler for market/government ticks
- Add per-action cooldowns
- Add IP/device fingerprint audit
- Add tests
