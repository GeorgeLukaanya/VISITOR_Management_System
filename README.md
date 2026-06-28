# Visitor Management — Phase 1 ("Replace the Book")

A **USSD-based visitor check-in system** for official buildings in Uganda
(government parastatals, banks, hospitals, courts, corporate offices). It digitally
replaces the **paper visitor's book** at reception.

A visitor dials a USSD code, enters the routing code of the company/tenant they're
visiting, picks a purpose, and their arrival is logged. A real-time alert reaches
the security guard and/or tenant — **sent off-session** (after the USSD session
ends). The visitor's phone number is captured automatically from the USSD session.
Tenants and building managers view and export **their own** visit logs.

> This README is the operational guide. [`CLAUDE.md`](./CLAUDE.md) remains the
> source of truth for project context, decisions, and guardrails.

---

## Table of contents

1. [Phase 1 scope](#phase-1-scope)
2. [Architecture](#architecture)
3. [Tech stack](#tech-stack)
4. [Data model](#data-model)
5. [Multi-tenancy & access control](#multi-tenancy--access-control)
6. [The USSD flow](#the-ussd-flow)
7. [Off-session notifications](#off-session-notifications)
8. [Dashboards & exports](#dashboards--exports)
9. [Privacy & data protection (Uganda DPA)](#privacy--data-protection-uganda-dpa)
10. [Requirements](#requirements)
11. [Quick start](#quick-start)
12. [Configuration reference](#configuration-reference)
13. [Make targets](#make-targets)
14. [Driving the USSD flow locally](#driving-the-ussd-flow-locally)
15. [HTTP endpoints](#http-endpoints)
16. [Testing](#testing)
17. [Troubleshooting](#troubleshooting)
18. [Africa's Talking integration (swappable)](#africas-talking-integration-swappable)
19. [Before live testing — checklist](#before-live-testing--checklist)
20. [Open decisions / stubs](#open-decisions--stubs)
21. [Project layout](#project-layout)
22. [People](#people)

---

## Phase 1 scope

**In scope ("Replace the Book"):**

1. **USSD check-in** — minimal input, a strict **two-screen** flow.
2. **Unique routing code per tenant** — the key a visitor enters to reach a company.
3. **Real-time notification** to tenant and/or guard on arrival — **off-session**.
4. **Tenant control** — each tenant views/exports **their own** logs (never cross-tenant).
5. **Building-manager dashboard** — building-wide logs, exportable.

**Explicitly OUT of scope (not built, not scaffolded for):**

- Identity verification against NIRA / telco SIM registry (Phase 2).
- Host pre-notification, badge printing, access-control hardware, analytics (Phase 3).
- Any flow longer than two USSD screens.
- Any in-session network calls to third parties.
- Courier / checkpoint flows (not specced).

---

## Architecture

```
                        ┌──────────────────────┐
   Visitor handset      │   Africa's Talking    │
        │ dials *code#  │  (USSD aggregator)    │
        └──────────────►│                       │
                        └───────────┬───────────┘
                       HTTP POST /ussd (each step)
                                    │
                                    ▼
        ┌───────────────────────────────────────────────┐
        │  nginx  ──fastcgi──►  app (php-fpm, Laravel)    │
        │                          │                      │
        │   UssdController ─► UssdSessionService (FSM)     │
        │        │ persist Visit (idempotent)             │
        │        │ dispatch NotifyVisitCheckedIn ──────┐  │
        │        └─ return "END ..." FAST              │  │
        └─────────────────────────────────────────────┼──┘
              │                    │                    │
              ▼                    ▼                    ▼
        ┌──────────┐        ┌──────────┐        ┌──────────────┐
        │ Postgres │        │  Redis   │◄───────│ queue worker │
        │ (visits) │        │ (queue)  │        │  runs the    │
        └──────────┘        └──────────┘        │  job:        │
              ▲                                  │  • SMS (AT)  │
              │                                  │  • broadcast │
        ┌─────┴──────┐                           └──────┬───────┘
        │ Dashboards │                                  │ Reverb event
        │ (Blade)    │                                  ▼
        │ tenant /   │                           ┌──────────────┐
        │ manager    │                           │   Reverb     │──► Guard tablet
        └────────────┘                           │  (websocket) │    (realtime)
                                                 └──────────────┘
```

**Key principle:** the USSD request handler does only *persist → dispatch → return
`END`*. All slow/external work (SMS, websocket push) happens **off-session** on the
Redis queue worker, so USSD sessions stay fast and never time out.

The Laravel app lives in [`./src`](./src); Docker/infra and docs live at the repo root.

---

## Tech stack

| Concern | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Datastore | PostgreSQL 16 (single DB, row-level multi-tenancy) |
| Queue / cache | Redis 7 |
| Realtime | Laravel Reverb (Pusher-compatible websockets) |
| SMS / USSD | Africa's Talking, behind a swappable `SmsGateway` interface |
| Dashboards | Plain Blade + controllers |
| Tests | Pest (in-memory SQLite) |
| Orchestration | Docker Compose (app, nginx, postgres, redis, queue, reverb) |

---

## Data model

Single PostgreSQL database; scoped tables carry `building_id` / `tenant_id`.

```
buildings ──1:N──► tenants ──1:N──► visits ◄──N:1── (building_id, denormalised)
    │                                  ▲
    ├──1:N──► guards                   │
    └──1:N──► users (managers)         │
                tenants ──1:N──► users (tenant admins)
```

| Table | Key columns |
|---|---|
| `buildings` | `name`, `address`, `manager_name`, `manager_phone` |
| `tenants` | `building_id`, `name`, **`routing_code` (unique)**, `contact_name`, `contact_phone`, `notify_tenant`, `notify_guard` |
| `guards` | `building_id`, `name`, `phone`, `device_token` |
| `visits` | `building_id`, `tenant_id`, `visitor_phone`, `purpose`, `status` (`checked_in`/`checked_out`/`auto_closed`), `checked_in_at`, `checked_out_at`, **`ussd_session_id` (unique)**, `notified_at` |
| `users` | `name`, `email`, `password`, `role` (`platform_admin`/`building_manager`/`tenant_admin`), `building_id`, `tenant_id` |

Notes:
- `visits.building_id` is **denormalised** from the tenant so building-manager
  queries and scoping never need a join.
- `visits.ussd_session_id` is **unique** → a retried/duplicate AT callback can never
  create a second visit.
- `visits.notified_at` is the **idempotency anchor** for the notification job.

Enums live in `app/Enums`: `UserRole`, `VisitStatus`, `VisitPurpose`
(`1 Meeting / 2 Delivery / 3 Interview / 4 Other`).

---

## Multi-tenancy & access control

One database, **row-level scoping**, enforced two ways:

1. **Global scope** — `App\Models\Scopes\TenantScope` (applied via the
   `BelongsToTenant` trait on `Visit`, `Tenant`, `Guard`). It reads the
   authenticated user and constrains every query:

   | Role | Sees |
   |---|---|
   | `tenant_admin` | only rows for their `tenant_id` |
   | `building_manager` | only rows for their `building_id` |
   | `platform_admin` | everything (no constraint) |
   | *unauthenticated* (USSD callback, queue worker, console, seeder) | **no scope** — these legitimately operate across tenants |

   Unknown/misconfigured roles **fail closed** (`1 = 0`).

2. **Policies** — `VisitPolicy`, `TenantPolicy`, `GuardPolicy`, `BuildingPolicy`
   add defence-in-depth so an explicitly-loaded record (route binding, guessed id)
   can't be viewed/exported across boundaries.

This is the core privacy guarantee: **a tenant can never read another tenant's
visits, even by guessing IDs.** Proven by `tests/Feature/TenantScopingTest.php`.

---

## The USSD flow

Two screens, exactly as in `CLAUDE.md`. State is derived **entirely from the
accumulated `text`** (steps joined by `*`) — sessions are stateless across POSTs.

| Step | `text` | Reply |
|---|---|---|
| Session start | *(empty)* | `CON Enter the code of who you are visiting:` |
| Code entered (valid) | `1001` | `CON You are visiting Acme Bank. Reason: 1. Meeting 2. Delivery 3. Interview 4. Other` |
| Code entered (invalid) | `9999` | `END Code not recognised. Please check the posted list.` |
| Purpose picked | `1001*1` | `END Thank you. Your arrival has been logged.` *(visit persisted + job dispatched)* |
| Purpose out of range | `1001*9` | `CON Invalid choice. You are visiting Acme Bank. Reason: ...` *(re-prompts; no third screen)* |

**Implementation:**
- `app/Services/Ussd/UssdSessionService` — a **pure** state machine (no I/O):
  returns a `UssdResponse` (`CON`/`END`) plus an optional `CheckInIntent`.
- `app/Http/Controllers/UssdController` — maps the AT payload → asks the service →
  on a `CheckInIntent`, persists the visit (`firstOrCreate` on `ussd_session_id`,
  idempotent) and dispatches `NotifyVisitCheckedIn` → returns plain text fast.

**Hard rules enforced:**
- Visitor phone comes from the session payload — **never asked for**.
- **No** SMS/websocket/external HTTP inside the USSD request.
- Always `200 OK` with a `CON`/`END` body, even on user errors.
- Codes match case-insensitively and trimmed.

Covered by `tests/Feature/UssdFlowTest.php` (empty start, valid/invalid code,
purpose selection, stateless mid-flow resume, invalid-purpose re-prompt, idempotent
re-post, 200/text-plain, case-insensitive codes).

---

## Off-session notifications

On a check-in the controller dispatches `App\Jobs\NotifyVisitCheckedIn` to the
Redis queue. The worker (`queue:work --tries=3 --backoff=5`) then:

1. **Sends one SMS** (a single bulk send) to the building guards (when
   `tenant.notify_guard`) and/or the tenant contact (when `tenant.notify_tenant`),
   via the configured `SmsGateway`.
   Message format (Phase 1 — phone + destination + time + purpose, **no registered
   name**): `Visitor +256... arrived for Acme Bank at 20:21 (Delivery).`
2. **Broadcasts** `App\Events\VisitCheckedIn` over Reverb on the per-building
   private channel `guards.building.{id}`, event name `visit.checked_in`, payload:
   `{ visit_id, visitor_phone, tenant, purpose, arrived_at }`.

**Idempotency** (`CLAUDE.md`: "never double-notify"): the job atomically *claims*
the visit by setting `notified_at` only when it is still null
(`whereNull(...)->update(...)`). A retried/duplicate run finds nothing to claim and
no-ops. If the send fails the claim is **released** so the queue can retry.

Covered by `tests/Feature/NotificationJobTest.php`.

---

## Dashboards & exports

Plain Blade. Log in at `/login` (no public registration — accounts are provisioned).

- **Tenant admin** → sees/exports only their tenant's visits.
- **Building manager** → sees/exports their whole building.
- **Platform admin** → sees/exports everything.

Scoping is automatic (the `Visit` global scope) and gated by `VisitPolicy`. The CSV
export streams with `chunkById` (flat memory) and the scope still applies, so the
export can only ever contain the caller's own rows
(`tests/Feature/DashboardTest.php`).

Seeded demo accounts (password `password`):

| Email | Role | Sees |
|---|---|---|
| `admin@example.com` | Platform admin | All buildings |
| `manager@example.com` | Building manager | Whole building |
| `acme@example.com` | Tenant admin | Acme Bank (code `1001`) |
| `umbrella@example.com` | Tenant admin | Umbrella Legal (code `1002`) |

---

## Privacy & data protection (Uganda DPA)

- Visitor phone numbers are **PII**. Row-scoping ensures one tenant never sees
  another's visit data.
- The whole point vs. the paper book: the **next visitor can't read the previous
  one's details** — there is no public/walk-up screen that exposes prior entries.
- **Production data must be Uganda-localised** (e.g. Raxio). The US cloud sandbox is
  **dev/testing only** and must hold **no real visitor data**. All secrets/DB config
  are env-driven so the same images redeploy onto local infra unchanged.
- A retention window is configurable via `VISIT_RETENTION_DAYS` and enforced by the
  `visits:prune` command, scheduled daily (02:30). A window of `0` retains
  indefinitely. Run it by hand with `php artisan visits:prune` (`--dry-run` to
  preview, `--days=N` to override the window).

---

## Requirements

There are two supported ways to run the app — pick one:

- **Docker + Docker Compose** (default). Nothing else is needed on the host —
  PHP, Composer, Postgres, Redis, etc. all run in containers. See
  [Quick start](#quick-start).
- **Native / no-Docker** (fast edits). PHP 8.2+ with the `pdo_pgsql`, `mbstring`,
  `intl`, `curl` and `xml` extensions, plus Composer and a Postgres instance.
  No Redis required — the native profile runs queue/cache/sessions on the
  database. See [Running without Docker](#running-without-docker).

---

## Quick start

> Verified on Docker 29.x / Compose 2.40. Run every command from the repo root
> (`Visitor_Management/`). The `make` targets are thin wrappers over
> `docker compose` — the raw command is shown next to each so you can run either.
>
> For the default logins/codes/ports and the **expected output of each step**,
> see [`RUNNING.md`](RUNNING.md).

### Step 0 — prerequisites

You only need **Docker** and the **Docker Compose** plugin on the host:

```bash
docker --version          # 24+ recommended
docker compose version    # v2.x (note: "compose", not the old "docker-compose")
```

Make sure the Docker daemon is running. On Linux you may need to be in the
`docker` group (or prefix commands with `sudo`).

### Step 1 — create the Laravel env file

Laravel reads **`src/.env`**. If it doesn't exist yet, copy the example
(`-n` so an existing file is never overwritten):

```bash
cp -n src/.env.example src/.env
```

The committed defaults already point at the compose service names
(`postgres`, `redis`, `reverb`) — no edits needed for local dev.

> The root-level **`.env`** is a *different* file: it only feeds host-side
> values into `docker-compose.yml` (published ports, DB credentials). Leave it
> as-is unless a port clashes — see [Configuration reference](#configuration-reference).

### Step 2 — build the images

```bash
make build          # = docker compose build
```

First build pulls PHP/Postgres/Redis/nginx base images, so it takes a few
minutes. Subsequent builds are cached and fast.

### Step 3 — start the stack

```bash
make up             # = docker compose up -d
```

This launches all six services in the background: `app` (php-fpm), `nginx`,
`postgres`, `redis`, `queue` (the off-session worker), and `reverb` (websockets).
Confirm they're all up:

```bash
docker compose ps
```

`vms_postgres` should report `(healthy)`; the rest should be `Up`.

### Step 4 — app key (first run only)

If `APP_KEY=` in `src/.env` is empty, generate one. (The committed example
already ships a dev key, so you can usually skip this.)

```bash
docker compose exec app php artisan key:generate
```

### Step 5 — migrate and seed demo data

```bash
make migrate        # = docker compose exec app php artisan migrate
make seed           # = docker compose exec app php artisan db:seed
```

This creates the schema and seeds one building, two tenants (with distinct
routing codes), and one guard. To wipe and rebuild from scratch later, use
`make fresh` (`migrate:fresh --seed`).

### Step 6 — verify it's working

```bash
# Web dashboard responds:
curl -I http://localhost:8088/login            # expect: HTTP/1.1 200 OK

# End-to-end USSD check-in (tenant code 1001, purpose 1 = Meeting):
docker compose exec app php artisan ussd:simulate --text="1001*1"
# expect: END Thank you. Your arrival has been logged.
```

You're up. Open the dashboard in a browser:

- **App / dashboard:** <http://localhost:8088>
- **Login:** <http://localhost:8088/login> — `acme@example.com` / `password`

### Everyday operations

```bash
make logs                       # tail all container logs (Ctrl-C to stop)
docker compose logs -f queue    # follow just the off-session worker
make tinker                     # Laravel REPL inside the app container
make down                       # stop the stack (data in the pgdata volume is kept)
docker compose down -v          # stop AND delete the database volume (full reset)
```

See [Make targets](#make-targets) for the full list.

---

## Running without Docker

For a tighter edit loop you can run the PHP app **directly on the host** while
still talking to Postgres. The native profile lives in **`src/.env.native`**:
Laravel loads it automatically whenever the shell variable `APP_ENV=native` is
set, so the Docker config in `src/.env` is left untouched — you can switch back
and forth freely.

The native profile **avoids Redis entirely** (queue, cache and sessions all run
on the database), so the only infra you need is Postgres. The off-session queue
stays genuinely async — jobs land in the `jobs` table and a separate worker
drains them, exactly as in the Docker setup.

### Option A — reuse the Dockerised Postgres (recommended, least setup)

Keep Postgres in Docker (it's already migrated and seeded) and run only the app
processes natively. `src/.env.native` points at `127.0.0.1:55432`, the host port
Compose publishes for Postgres.

```bash
# 1. Make sure Postgres is up (the rest of the stack can stay down):
docker compose up -d postgres

# 2. In three separate terminals (or use the Make targets below):
make serve      # → http://localhost:8000
make worker     # off-session notification queue worker
make realtime   # Reverb websocket server on :8080  (only if testing the tablet)
```

> If you skip `make realtime`, the SMS/log notification still fires, but the
> websocket broadcast job (`VisitCheckedIn`) will fail and land in `failed_jobs`
> — harmless in dev. Start `make realtime` when you want the guard-tablet
> push to succeed.

### Option B — fully Docker-free

Use a native Postgres instead. Create the database/role, point `src/.env.native`
at it (e.g. `DB_PORT=5432`), then migrate and seed:

```bash
createdb visitor_management            # or psql: CREATE DATABASE / CREATE ROLE
# edit src/.env.native → DB_HOST/DB_PORT/credentials for your local Postgres
make native-migrate                    # migrate --seed against the native DB
make serve                             # + make worker, make realtime as needed
```

### Native equivalents of the everyday commands

Every artisan command works natively — just prefix it with `APP_ENV=native`
from the `src/` directory:

```bash
cd src
APP_ENV=native php artisan migrate
APP_ENV=native php artisan db:seed
APP_ENV=native php artisan ussd:simulate --text="1001*1"
APP_ENV=native ./vendor/bin/pest
APP_ENV=native php artisan tinker
```

- App: <http://localhost:8000> (note: **8000**, not the Docker port 8088)
- Login: same seeded users (`acme@example.com` / `password`)

> **Don't run Docker and native against the same database at once** if it
> matters to you — Option A shares the Dockerised Postgres, so a check-in made
> natively shows up in the Docker dashboard and vice-versa. That's usually handy,
> but be aware they're the same data.

---

## Configuration reference

There are **three `.env` files, by design**:

- **`src/.env`** — the Laravel app config used by the **Docker** stack (hosts are
  Compose service names: `postgres`, `redis`, `reverb`).
- **`src/.env.native`** — overrides used by the **native** run (`APP_ENV=native`);
  hosts are `127.0.0.1`, and queue/cache/sessions use the database (no Redis).
  See [Running without Docker](#running-without-docker).
- **root `.env`** — docker-compose variable interpolation only (host ports, UID).

### `src/.env` — the Laravel application config

| Variable | Default | Purpose |
|---|---|---|
| `APP_URL` | `http://localhost:8088` | Base URL |
| `DB_CONNECTION` | `pgsql` | Primary datastore |
| `DB_HOST` / `DB_PORT` | `postgres` / `5432` | Compose service name |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `visitor_management` / `vms` / `secret` | Credentials |
| `QUEUE_CONNECTION` | `redis` | Off-session jobs |
| `CACHE_STORE` | `redis` | Cache |
| `REDIS_HOST` / `REDIS_PORT` | `redis` / `6379` | Compose service name |
| `BROADCAST_CONNECTION` | `reverb` | Realtime driver |
| `REVERB_APP_ID/KEY/SECRET` | `vms-local*` | Reverb credentials |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | `reverb` / `8080` / `http` | Reverb server (container) |
| `VITE_REVERB_*` | derived | Browser/tablet client connection (host ports) |
| `AT_DRIVER` | `log` | SMS driver: `log` (no network) or `africastalking` |
| `AT_USERNAME` / `AT_API_KEY` / `AT_SENDER_ID` | `sandbox` / – / – | Africa's Talking SMS credentials |
| `AT_USSD_SERVICE_CODE` | `*384*1234#` | Dialled USSD code |
| `AT_CALLBACK_SECRET` | – | Shared secret to authenticate AT's callback. Empty = open (default); when set, the callback requires it via `X-Callback-Secret` header or `?secret=` query param, else 403 |
| `VISIT_RETENTION_DAYS` | `180` | DPA retention window, enforced daily by `visits:prune` (`0` = keep forever) |

> The container intentionally does **not** export these as real process env vars —
> see the [testing note](#testing) for why that matters.

### root `.env` — docker-compose interpolation only

| Variable | Default | Purpose |
|---|---|---|
| `UID` / `GID` | `1000` | Run containers as the host user (writable bind mounts) |
| `APP_PORT` | `8088` | Published host port → nginx |
| `DB_FORWARD_PORT` | `55432` | Published host port → postgres |
| `REDIS_FORWARD_PORT` | `56379` | Published host port → redis |
| `REVERB_FORWARD_PORT` | `8081` | Published host port → reverb |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | matches `src/.env` | Postgres init |

> Host ports default to non-standard values to avoid clashing with services already
> running on your machine (e.g. a local Postgres on 5432). Change freely.

---

## Make targets

| Command | What it does |
|---|---|
| `make build` | Build the PHP image |
| `make up` / `make down` | Start / stop the stack |
| `make install` | `composer install` + copy `.env` + `key:generate` (first run) |
| `make migrate` | Run migrations |
| `make seed` | Seed 1 building, 2 tenants (codes 1001/1002), 1 guard, 4 users |
| `make fresh` | `migrate:fresh --seed` |
| `make test` | Run the Pest suite |
| `make tinker` | Tinker REPL |
| `make serve` | **[native]** Serve the app on <http://localhost:8000> |
| `make worker` | **[native]** Run the off-session queue worker |
| `make realtime` | **[native]** Run the Reverb websocket server |
| `make native-migrate` | **[native]** `migrate --seed` against the configured DB |
| `make ussd ARGS="..."` | Drive the USSD flow (e.g. `ARGS="--text=1001*1"`) |
| `make logs` | Tail container logs |

---

## Driving the USSD flow locally

The `ussd:simulate` command POSTs Africa's-Talking-shaped payloads at our own
callback, so you can walk the whole flow without a telco.

```bash
# Interactive — emulates a handset, prompts for each screen:
docker compose exec app php artisan ussd:simulate

# Scripted — replay accumulated `text` in one shot:
docker compose exec app php artisan ussd:simulate --text="1001*1"

# Options:
#   --phone="+2567..."     visitor MSISDN (AT captures this from the SIM)
#   --session="abc"        reuse a session id (default: random)
#   --service-code="*384#" dialled code
#   --url="https://.../ussd"  target a different endpoint (e.g. AT sandbox)
```

Point it at the **AT USSD simulator / sandbox** once Steven provides access:

```bash
docker compose exec app php artisan ussd:simulate --url="https://<your-ngrok-host>/ussd"
```

The real AT callback URL to register in the AT dashboard is `https://<host>/ussd`.

---

## HTTP endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/ussd` | none (CSRF-exempt) | Africa's Talking USSD callback. Body: `sessionId`, `phoneNumber`, `serviceCode`, `text`. Returns `text/plain` `CON ...`/`END ...`, always `200`. |
| `GET` | `/` | none | Redirects to `/dashboard` |
| `GET` | `/login` | guest | Login form |
| `POST` | `/login` | guest | Authenticate (`email`, `password`, `remember`) |
| `POST` | `/logout` | auth | Log out |
| `GET` | `/dashboard` | auth | Scoped visit list |
| `GET` | `/dashboard/export` | auth | Scoped CSV download |
| `GET` | `/up` | none | Laravel health check |

**Reverb channel:** `private-guards.building.{buildingId}`, event `visit.checked_in`.
Authorized (Phase 1) for that building's manager and platform admins.

---

## Testing

```bash
make test     # or: docker compose exec app ./vendor/bin/pest
```

**40 tests** covering: tenant scoping/isolation, every USSD transition,
notification job (one SMS + one broadcast, no double-send, claim release on
failure), dashboard scoping + CSV export, auth, the USSD callback-secret guard,
and the visit-retention prune command.

Tests run against an **in-memory SQLite** database, isolated from the dev Postgres.

> ⚠️ **Why the container doesn't set `DB_CONNECTION` (etc.) as real env vars:**
> a real `DB_CONNECTION` environment variable beats PHPUnit's `<env force="true">`
> settings. If it's set, the suite runs against the live Postgres and
> `RefreshDatabase` **wipes your data** every run. All config therefore lives in
> `src/.env` (a file, loaded immutably) — never in the compose `environment:` block.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| **`502 Bad Gateway`** after recreating the `app` container | nginx cached the old container IP. The config uses Docker's embedded resolver to re-resolve automatically; if it persists, `docker compose restart nginx`. |
| **Port already in use** on `up` | Another service holds the port. Change `APP_PORT` / `DB_FORWARD_PORT` / `REDIS_FORWARD_PORT` / `REVERB_FORWARD_PORT` in the **root** `.env`. |
| **`could not find driver`** running tests/artisan on the host | Run inside the container (`docker compose exec app ...`). The host PHP lacks `pdo_pgsql`/`pdo_sqlite`; the image has them. |
| **Tests wipe seeded data** | Ensure no `DB_*` env vars are set in the compose `environment:` block (see testing note). |
| **Postgres `Created` but not `Up`** | Start it first: `docker compose up -d postgres`, wait for healthy, then `docker compose up -d`. |
| **Reverb fails to boot** | Ensure `REVERB_APP_ID/KEY/SECRET` are set in `src/.env`. |
| **Broadcast job fails with `ModelNotFound`** | The visit was deleted (e.g. DB wiped) before the queued broadcast ran. Fix the data/isolation issue above. |

---

## Africa's Talking integration (swappable)

All AT mechanics follow the **standard convention documented in `CLAUDE.md`** and
are treated as *verify-against-official-docs*. They sit behind small surfaces so
confirmed details are a one-line change:

- **SMS** → `App\Contracts\SmsGateway`, resolved in `AppServiceProvider` from
  `AT_DRIVER`:
  - `log` (default) → `LogSmsGateway` — writes messages to the log, no network, no
    credentials. The sandbox sends no real SMS.
  - `africastalking` → `AfricasTalkingSmsGateway` — real bulk-SMS API.
- **USSD** → `App\Services\Ussd\UssdSessionService` is a pure state machine; the
  controller only maps the AT payload, persists, dispatches, and returns fast. If
  the confirmed AT field names differ, only the controller's mapping changes.

---

## Before live testing — checklist

Needs real aggregator access (Steven, action #1):

- [ ] **Confirm the AT USSD callback contract** (`sessionId`, `phoneNumber`,
      `serviceCode`, `text`; `CON`/`END` reply). Update `UssdController`/`UssdRequest`
      only if the confirmed docs differ.
- [ ] **AT sandbox + USSD service code** — register the callback URL, get a shared
      code, drive the AT USSD simulator.
- [ ] **AT SMS credentials** (`AT_USERNAME`, `AT_API_KEY`, optional `AT_SENDER_ID`),
      then set `AT_DRIVER=africastalking`. Verify the bulk-SMS endpoint/fields.
- [ ] **Confirm callback authentication** — `AT_CALLBACK_SECRET` is now enforced
      when set (`X-Callback-Secret` header or `?secret=` query param → else 403);
      confirm which mechanism AT actually uses and prune the unused branch.
- [ ] **Reverb/guard-tablet device auth** — Phase 1 authorizes the per-building
      websocket channel for dashboard users; a dedicated guard-tablet (token →
      `Guard`) auth model isn't specced yet.
- [ ] **Production hosting** — deploy onto Uganda-localised infra; keep the sandbox
      free of real visitor data.

---

## Open decisions / stubs

| Item | Status | Owner |
|---|---|---|
| **Routing-code uniqueness** | Globally unique for now; per-building is a small documented change (flagged in `create_tenants_table`) | Arthur (Q1/Q4) |
| **Visit retention prune** | Built: `visits:prune` command, scheduled daily, driven by `VISIT_RETENTION_DAYS` | Confirm the retention policy/window with client |
| **Invalid USSD purpose UX** | Re-shows the menu (stays within two screens) instead of ending | Confirm desired UX |
| **Guard-tablet auth** | Channel authorized for dashboard users; device-token auth model TBD | When specced |
| **Session/per-building pricing** | Does not block the build | Steven (Q2) / Arthur |
| **Courier / checkpoint flows** | Not built (out of Phase 1) | Arthur (Q5/Q6) |

---

## Project layout

```
.
├── CLAUDE.md              # source-of-truth project context & guardrails
├── README.md             # this file
├── docker-compose.yml    # app, nginx, postgres, redis, queue, reverb
├── Makefile              # up / migrate / seed / test / tinker / ussd
├── .env                  # docker-compose interpolation (ports, UID/GID)
├── docker/
│   ├── php/Dockerfile    # php-fpm + pdo_pgsql, pdo_sqlite, redis, bcmath, pcntl
│   └── nginx/default.conf
└── src/                  # the Laravel application
    ├── app/
    │   ├── Console/Commands/           # SimulateUssd, PruneVisits (retention)
    │   ├── Contracts/SmsGateway.php
    │   ├── Enums/                       # UserRole, VisitStatus, VisitPurpose
    │   ├── Events/VisitCheckedIn.php    # Reverb broadcast
    │   ├── Http/Controllers/            # Ussd, Dashboard, Auth/Login
    │   ├── Http/Middleware/             # VerifyUssdCallback (AT_CALLBACK_SECRET)
    │   ├── Jobs/NotifyVisitCheckedIn.php
    │   ├── Models/                      # + Scopes/TenantScope, Concerns/BelongsToTenant
    │   ├── Policies/                    # Visit, Tenant, Guard, Building
    │   ├── Providers/AppServiceProvider.php   # binds SmsGateway by driver
    │   ├── Services/Ussd/               # UssdSessionService (state machine) + DTOs
    │   └── Sms/                         # LogSmsGateway, AfricasTalkingSmsGateway
    ├── database/migrations|factories|seeders
    ├── resources/views/                 # layouts/app, auth/login, dashboard/index
    ├── routes/web.php|channels.php
    └── tests/                           # Pest (40 tests)
```

---

## People

- **Steven** — business/commercial, AT docs + sandbox access, telco negotiation,
  data-protection registration.
- **Arthur** — client-side confirmations (price, routing codes, courier, checkpoints).
- **Team** (the.seeker.47, Elroy, others) — the build. First-time USSD integrators —
  the USSD layer is heavily commented for this reason.
