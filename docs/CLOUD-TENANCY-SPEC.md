# Cloud tenancy: architecture decision and implementation spec

## Goal

Host the POS online for multiple restaurants. Give each restaurant a subdomain, a separate database, and an expiry date for its subscription. Keep ordering, stock, payments, and real-time updates private to that restaurant. Provision and renew customers without editing application code.

## Current state verified in the repositories

- The Laravel API uses one environment-configured database connection. Its 46 existing migrations and models have no tenant context or subscription record.
- Login is public at `/api/login`; the remaining API routes use JWT. Tenant selection and expiry checks must therefore run before login and JWT authentication.
- Scheduled inventory commands, queued jobs/notifications, cache entries, and broadcast events exist. Several broadcast channels use shared names such as `inventory` and `orders`.
- The Nuxt frontend has a localhost API URL in `nuxt.config.ts` and a WebSocket URL derived from the current host with port 6001 in `plugins/echo.client.js`. Cloud deployment must configure both.
- CORS currently includes a wildcard origin while credentials are enabled. The cloud origin policy needs an explicit design.

## Architecture options

| Concern | A. Separate Laravel deployment and DB per restaurant | B. One Laravel deployment, separate DB per restaurant |
| --- | --- | --- |
| Initial application code | Lower: existing models can keep using the default connection. Add subscription check and deployment configuration. | Higher: resolve tenant from a trusted host, load central tenant registry, switch DB before auth/model binding, and reset context safely. |
| Customer onboarding | Create subdomain, site/runtime configuration, DB/user, migrations, seed data, storage, secrets, TLS, and monitoring for each customer. Automate before the customer count grows. | Create central registry row, DB/user, migrations, seed data, DNS/TLS coverage. One application runtime serves all hosts. |
| Updates | Apply the same release/migrations to every deployment, with per-customer rollout and rollback. Operational work grows with customer count. | Deploy code once, but run tenant migrations across every DB and handle partial failures. |
| Runtime resources | Can share a server and PHP-FPM pool, so a separate deployment does not necessarily mean one server/process per customer. Separate runtime configuration, caches, workers, and WebSocket setup still add overhead. | Less duplicate application setup; one shared runtime. Database connections, jobs, cache, and broadcasts still need tenant limits and isolation. |
| Isolation | Database and deployment configuration are naturally separate. Distinct secrets, storage, cache namespace, and WebSocket channels are still required. | Every request, worker job, scheduled command, cached value, file, and broadcast must carry tenant identity correctly. A missed boundary can expose another restaurant's data. |
| Subscription expiry | An expiry date can live in that restaurant's DB, but renewal/control must reach each DB. A central registry is cleaner for operator-managed billing. | Central registry is already required to map hosts to DBs; keep expiry there and reject expired tenants before accessing their DB. |
| Failure scope | A bad per-customer deployment can affect one restaurant; shared infrastructure failures can still affect all. | A failed deployment or shared-runtime incident can affect all restaurants. |

### Complexity assessment for this codebase

Option A is **lower implementation complexity** for the first few restaurants and **higher recurring deployment effort**. Option B is **higher implementation and security-review complexity** now and **lower repeated application deployment effort** as restaurant count grows. Exact server capacity depends on traffic, worker settings, and database size; it cannot be inferred from repository code alone.

## Selected rollout decision

The owner selected **A: a separate backend deployment, subdomain, and database per restaurant**, with **one shared Nuxt frontend deployment**. The frontend has the fixed origin `https://maresto.eminent-studio.com`; a restaurant backend uses `https://cafename.eminent-studio.com`, replacing `cafename` with its assigned slug. Keep all backend deployments on the same versioned release; each deployment has its own environment, database user/database, app/JWT secrets, storage, and worker/WebSocket process. Do not manually edit or copy a divergent source tree per customer.

The frontend selection flow is also decided: read the restaurant slug from browser `localStorage`; if absent, show a popup asking for the slug. Validate and normalize the slug, save it, and direct API/WebSocket traffic to that restaurant backend. Changing the slug must clear the current user's token and restaurant-specific client state before another login.

The restaurant subscription expiry lives in its own database, as requested. The operator updates it through a server-side Artisan command, not a POS API route. A central billing/customer registry is outside this first implementation. Move to B only if later deployment/operating effort justifies its added isolation work.

## Requirements shared by either option

1. Resolve the restaurant only from a validated host/subdomain supplied through a trusted proxy. Unknown hosts cannot reach POS data. Reserve an operator/admin host outside tenant routing.
2. Keep one POS database and database user per restaurant. Never accept the DB name, tenant ID, or subscription status from a client request as authority.
3. Define expiry as a restaurant subscription (`expires_on`, UTC date), not an individual POS user's expiry. The date is inclusive: access is blocked starting 00:00 UTC the next day. Return a stable API response for expired subscriptions, including on login. Do not expose renewal controls through restaurant-admin credentials.
4. Bind JWTs to a restaurant or use a distinct signing secret per deployment. A token from restaurant A must fail at restaurant B even if user IDs match.
5. Scope files, cache/permission state, jobs, notifications, scheduled commands, and WebSocket channels to one restaurant. Existing public broadcast channel names require review before sharing a WebSocket server.
6. Configure cloud API/WebSocket origins and TLS for the chosen Nuxt topology. Remove localhost assumptions and use an explicit CORS origin policy. Browser receipt printing remains a separate local-printer-bridge concern.
7. Provide a repeatable provisioning, renewal, migration, backup, restore, and offboarding procedure. A failed tenant migration must be visible and recoverable; do not silently mark a tenant ready.
8. Existing single-restaurant installations need a documented migration path with backup and a clear domain/database assignment.

## Selected implementation scope

- Add a one-row subscription table in each restaurant DB, a server-only command to set its expiry date, and an expiry gate for all tenant API routes, including login. A missing record fails closed in production; local development can proceed before subscription setup.
- Require a configured API host in production and reject requests arriving on another host before querying POS data.
- Parameterize per-customer deployment settings: host, DB credentials, JWT secret, app key, storage/cache namespace, and broadcast configuration. Every backend allows the same exact frontend origin, `https://maresto.eminent-studio.com`.
- Provide a repeatable provisioning and renewal runbook. Run existing migrations per customer; do not create a new restaurant by copying a modified source tree.
- Provide a server-only first-admin command; never run production with the demo `UserSeeder` credentials. Disable the four-digit PIN login on public production endpoints by default.
- Document the shared Nuxt frontend contract for its developer. Do not edit the frontend repository in this change.

## Option B additional implementation scope

- Add a central tenant registry and an early API middleware that resolves the host and switches the DB before login, JWT, throttling identity, and route model binding. Fail closed for unknown, suspended, or expired tenants.
- Bind and validate the tenant in JWT claims. Clear per-request authentication, connection, and permission/cache context, especially if long-lived workers are introduced.
- Carry tenant identity through every queued job, notification, scheduler command, event, cache key, file path, and WebSocket subscription. Replace or scope shared public channels.
- Add tenant-aware migrations and provisioning so one failed tenant DB cannot corrupt another or hide a partial rollout.

## Review and acceptance criteria for either implementation

- Two restaurants with overlapping user/order IDs cannot read, authenticate to, receive broadcasts from, or modify each other's data.
- Unknown host and expired restaurant cannot log in or call protected POS endpoints. A renewed restaurant resumes without changing POS records.
- Existing active restaurant behavior remains compatible with current order, payment, inventory, and receipt APIs.
- Onboarding is repeatable without source edits; backup and restore target exactly one restaurant's POS DB and files.
- The Nuxt frontend reaches its tenant's cloud API and WebSocket endpoint over TLS. Cross-origin requests are limited to intended origins.
- A browser with no saved slug shows the restaurant popup before making POS requests; a saved valid slug selects the matching backend. Invalid slugs are rejected, and changing restaurants clears the previous token and cached POS state.
- The implementation diff is reviewed against this spec before deployment.

## Deployment details still chosen by the operator

1. DNS/TLS provider, server/hosting provider, process manager, and backup location. This spec does not create live cloud resources without those details.
