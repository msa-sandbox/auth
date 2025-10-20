# Auth Microservice

Authentication and authorization microservice built with Symfony. Provides JWT-based authentication and granular permission management for distributed systems.

## Overview

This microservice handles user authentication, permission management, and token lifecycle for web and API clients. It's designed as a standalone service within a microservice architecture, where other services rely on it for user verification and access control.

**Key responsibilities:**
- User authentication (login/logout/refresh)
- JWT token issuance and validation
- Granular permission management (entity-level CRUD permissions)
- Event propagation to other services via Kafka

## Local Development

The development environment uses **Docker Compose** with the following services:

- **PHP-FPM** (8.2) — runs the Symfony application
- **MySQL** (8.x) — primary database for users and permissions
- **Redis** — stores Prometheus metrics and caching
- **Nginx** — HTTP server and reverse proxy
- **Kafka** (via external infrastructure repo) — event streaming

### Quick Start

```bash
    # Copy environment file
    cp .env.local.example .env.local
    
    # Start services
    docker compose up --build
    
    # Load fixtures (optional)
    docker compose exec app php bin/console doctrine:fixtures:load
    
    # Run checks
    docker compose exec app composer check
```

Application will be available at [https://localhost:8081](https://localhost:8081).

## Technology Stack

- **Symfony 7.3** – framework (routing, DI, validation)
- **Doctrine ORM 3.x** – database persistence
- **MySQL 8.x** – relational database
- **LexikJWTAuthenticationBundle** – JWT token creation/validation
- **RdKafka** – Kafka producer/consumer
- **Prometheus Metrics Bundle** – custom metrics collection
- **PHPStan (level 5) / PHP CS Fixer / PHPUnit** – code quality tools

## Core Features

### Authentication

#### Web Client Authentication
- **Stateless JWT tokens** for access
- **Refresh token rotation** (stored in MySQL, HttpOnly cookies)
- **Rate limiting** on login/refresh endpoints (per-IP and per-user)
- Token TTL: 1 hour (access), 7 days (refresh)

#### CRM API Authentication
The service provides a **secure token exchange flow** for external CRM systems, allowing users to authorize CRM access without sharing credentials.

**Token Types:**
1. **Exchange Token** (10 min, one-time use)
   - Short-lived, single-use token generated per user request
   - Hash stored in DB (not plaintext) for security
   - Used to bootstrap CRM authentication

2. **Access Token** (24 hours, JWT)
   - Standard JWT for API requests
   - Contains `user_id` and permissions in payload
   - Especially long live to make auth invalidation logic more interesting

3. **Refresh Token** (30 days, JWT with jti)
   - JWT containing `jti` (token ID), `user_id`, `exp`
   - `jti` stored in MySQL for revocation capability
   - **Stateful**: validated against database on each refresh
   - **Rotate-on-use**: old token revoked when new pair issued


**Why stateful refresh tokens for CRM?**

Unlike web clients (where refresh tokens are in HttpOnly cookies), CRM systems store tokens locally. Stateful tokens with DB validation allow:
- Immediate revocation if compromised
- Audit trail of token usage
- Token rotation prevents replay attacks

See `docs/crmAuthFlow.puml` for the complete authentication sequence.

### Permission System
The service implements a **granular permission model** instead of traditional role-based access control (RBAC). Permissions are defined at the entity level with specific actions.

**Structure:**
```json
{
  "crm": {
    "access": {
      "web": true,
      "api": false
    },
    "permissions": {
      "lead": {
        "read": true,
        "write": true,
        "delete": false
      },
      "contact": {
        "read": true,
        "write": false,
        "delete": false
      }
    }
  }
}
```

**Key concepts:**
- **Scopes** (`crm`, future: `chat`, `analytics`) — logical groupings of permissions
- **Entities** (`lead`, `contact`, `deal`) — domain objects within a scope
- **Actions** (`read`, `write`, `delete`) — operations on entities
- **Access flags** (`web`, `api`, `all`) — control where permissions apply
- **Hierarchy enforcement** — `delete` requires `write` + `read`, `write` requires `read`

**Permission validation:**
- At least one entity must have `read=true` if access is granted
- Both `web=false` and `api=false` is valid (revokes all access)
- Empty permissions with access flags is an error

**Why permissions over roles?**
Roles become inflexible as systems grow. A user might need read access to leads but write access to contacts — traditional roles (Admin, Manager, Viewer) can't express this granularity without role explosion. Permissions allow precise, scalable access control.

### Kafka Event Propagation

When permissions change, the service publishes events to Kafka so other microservices can update their caches or deny access.

**Event format:**
```json
{
  "event": "user.permissions.changed",
  "user_id": 123,
  "changed_at": "2025-01-15T10:30:00+00:00"
}
```

**Critical design decision: Transactional rollback on Kafka failure**

When permissions are updated, the system wraps both the database write and Kafka publish in a transaction:
- If Kafka publish **fails**, the database transaction is **rolled back**
- This ensures consistency: other services never work with stale permission data

**Why rollback instead of retry?**

Permissions are **critical security data**. If Service A updates permissions but Service B doesn't receive the event, users might retain access they shouldn't have (or lose access they should have). This is a security vulnerability.

Alternative approaches considered:
1. **Queue + Retry** — risks delayed propagation; complex state management
2. **Accept eventual consistency** — unacceptable for authorization

By using rollback, we guarantee atomicity: permissions change everywhere or nowhere. The trade-off is occasional `500 Internal Error` responses if Kafka is down, which is acceptable for admin-only operations.

See `docs/roleUpdate.puml` for the sequence diagram.

## Observability

### Logging
Structured JSON logs (via Monolog) written to stdout. All exceptions are logged with stack traces, request context, and log levels:
- `INFO` — authentication failures (audit trail)
- `WARNING` — business logic errors (LogicException, validation failures)
- `ERROR` — infrastructure errors (Kafka, database failures)

Logs include:
- Exception type, message, code
- Cleaned file paths (relative to project root)
- Limited stack traces (configurable depth: 3-15 frames)
- Request URI, method, IP (for security events)

Helper: `ExceptionFormatter` formats exceptions into structured arrays for logging.

### Metrics
Prometheus metrics exposed at `/metrics`. Custom metrics:

**Security metrics:**
- `auth_login_attempts_total{status="success|failure", reason="wrong_password|user_not_found|rate_limited"}` — login attempt tracking
- Use for alerting on brute-force attacks (e.g., >10 failures from one IP in 5 min)

**Infrastructure metrics:**
- `auth_kafka_send_failures_total` — Kafka publish failures
- Alert when > 0 (indicates permission changes being blocked)

**HTTP metrics (built-in):**
- `auth_http_2xx|4xx|5xx_responses_total{action="..."}` — response codes by endpoint
- `auth_request_durations_histogram_seconds{action="..."}` — latency distribution

**Session metrics:**
- `auth_active_sessions` — gauge for monitoring refresh token count

### Health Checks
`GET /health` — returns HTTP 200 if application is healthy. Also checking database/Kafka connectivity.

## Key Design Decisions

### 1. Refresh Token Storage in Database
Refresh tokens are stored in MySQL (not Redis) for durability. If cache is cleared, users don't lose their sessions. Trade-off: slight latency increase, but acceptable for refresh operations (not called frequently).

### 2. Empty Permissions Validation
When `web=false` and `api=false`, this is **allowed** (revoke all access without deleting user). However, `web=true` with empty permissions is **rejected** (must grant at least one permission).

### 3. Permission Hierarchy Enforcement
The system automatically applies hierarchy (write adds read, delete adds write+read) **before** validation. This prevents invalid states like "user can delete but not read."

### 4. Cookie-Based Refresh Tokens (Web)
Web clients receive refresh tokens in HttpOnly, Secure, SameSite cookies. This prevents XSS attacks on refresh tokens. Access tokens are returned in JSON (stored in memory by frontend).

### 5. No /validate-token Endpoint
Other services validate JWT tokens themselves using the public key (asymmetric RS256). This avoids a central bottleneck. The auth service only issues tokens, doesn't validate them.

### 6. Rate Limiting Strategy
- **Per-IP limiter** on login/refresh — prevents distributed attacks
- **Per-user limiter** on login — prevents targeted brute-force on known accounts
- Both must pass for request to proceed

## API Endpoints

### Web Authentication (Public)
- `POST /web/login` — email + password → JWT + refresh cookie
- `POST /web/refresh` — refresh cookie → new JWT + new refresh cookie
- `POST /web/logout` — revoke refresh token

### CRM Authentication (Public)
- `POST /api/exchange-token` — exchange token → access JWT + refresh JWT
  - Body (OAuth 2.0 format):
    ```json
    {
      "grant_type": "exchange_token",
      "exchange_token": "uuid.random..."
    }
    ```
  - Returns:
    ```json
    {
      "access_token": "eyJhbGc...",
      "refresh_token": "eyJhbGc...",
      "token_type": "Bearer",
      "expires_in": 3600,
      "refresh_expires_in": 2592000
    }
    ```
  - Rate limit: 10 req/min per IP

- `POST /api/refresh` — refresh JWT → new access + refresh JWT (with rotation)
  - Body (OAuth 2.0 format):
    ```json
    {
      "grant_type": "refresh_token",
      "refresh_token": "eyJhbGc..."
    }
    ```
  - Returns:
    ```json
    {
      "access_token": "eyJhbGc...",
      "refresh_token": "eyJhbGc...",
      "token_type": "Bearer",
      "expires_in": 86400,
      "refresh_expires_in": 2592000
    }
    ```
  - Rate limit: 60 req/min per IP
  - **Note:** Old refresh token is automatically revoked

### User Management (Authenticated)
- `POST /web/user/{id}/token` — generate CRM exchange token (requires `ROLE_USER`, self or admin)
  - Returns: `{token, expires_at, ttl: 600}`
  - Rate limit: 10 req/min per user

- `GET /web/users` — list all users (requires `ROLE_USER`)
- `GET /web/user/{id}/permissions` — get user permissions (requires `ROLE_ADMIN`)
- `PUT /web/user/{id}/permissions` — update user permissions (requires `ROLE_ADMIN`)
- `GET /web/users/roles` — list available roles (legacy, may be deprecated)

### System
- `GET /health` — health check
- `GET /metrics` — Prometheus metrics
- `GET /` — API info (name, version)

## Commands

```bash
# Kafka consumer (listens for events) (not relevant in this project, but nice for test)
php bin/console kafka:consume

# Cleanup old sessions and expired tokens
# - Refresh sessions older than 90 days
# - Expired CRM exchange tokens
# - Expired CRM refresh tokens
php bin/console session:cleanup

# Code quality
composer style      # PHP CS Fixer
composer stan       # PHPStan (level 5)
composer unit       # PHPUnit tests
composer check      # All checks
```

## Architecture Notes

The project follows **clean architecture principles** without full DDD (the project is one domain). Key layers:
- `Http/` — controllers, DTOs, responses, event listeners
- `Service/` — business logic (stateless, readonly)
- `Repository/` — data access interfaces and implementations
- `Entity/` — Doctrine entities (User, RefreshSession, UserPermission)
- `Security/` — authentication provider, JWT handling, permission definitions
- `Infrastructure/` — external integrations (Kafka producer/consumer)
- `Metrics/` — Prometheus metric collectors

Services are **readonly** (immutable dependencies), improving testability and preventing accidental state mutation.

## Testing Strategy

- **Unit tests** for services, helpers, DTOs
- **Mocks** for repository and external dependencies
- Test coverage focuses on business logic (UserPermissionService, AuthService, ExceptionFormatter)
- Fixtures available for local testing

## Future Improvements

- Implement user management endpoints (create/delete/update users)

## Contributing

Run checks before committing:
```bash
composer check
```

Ensure all tests pass and no PHPStan errors remain.

## Related Documentation

- `docs/roleUpdate.puml` — Permission update flow with Kafka transaction rollback
- `docs/crmAuthFlow.puml` — CRM authentication flow with token exchange and rotation
- [MSA Infrastructure](https://github.com/msa-sandbox/infrastructure) — Shared infrastructure (Kafka, monitoring)
- [MSA overview](https://github.com/msa-sandbox) — Other microservices in the architecture
