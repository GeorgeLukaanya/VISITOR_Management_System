# CLAUDE.md — Visitor Management SaaS (Phase 1: "Replace the Book")

> This file is read by Claude Code at the start of every session. It is the
> source of truth for project context, decisions, and guardrails. Keep it
> short, current, and honest. If a decision changes, change it here.

## What we are building

A **USSD-based visitor check-in system** for official buildings in Uganda
(government parastatals, banks, hospitals, courts, corporate offices). It
digitally replaces the **paper visitor's book** at reception.

A visitor dials a USSD code, enters the code of the company/tenant they are
visiting, confirms their purpose, and their arrival is logged. A real-time
alert reaches the security guard and/or tenant. The visitor's phone number is
captured automatically from the USSD session.

### Phase 1 scope is deliberately narrow — "Replace the Book"
Do **not** build the full long-term vision. Phase 1 = the MVP only:

1. **USSD check-in** — minimal input, constrained to a **two-screen flow**.
2. **Unique routing code per tenant** — the key a visitor enters to reach a
   specific company in the building.
3. **Real-time notification** to the relevant tenant and/or security guard on
   arrival — **sent off-session** (after the USSD session ends, not during).
4. **Tenant control** — each tenant views who is coming and exports **their
   own** visitor logs (tenant-scoped, never cross-tenant).
5. **Building-manager dashboard** — building-wide visitor logs, exportable.

### Explicitly OUT of Phase 1 (do not build, do not scaffold for)
- Identity verification against NIRA / telco SIM registry (this is Phase 2).
- Host pre-notification, badge printing, access-control hardware integration,
  analytics dashboards (Phase 3).
- Any flow longer than two USSD screens.
- Any in-session network calls to third parties (keep sessions fast — see USSD
  rules below).

## Tech stack (locked)

- **PHP / Laravel** (latest stable, target Laravel 11+).
- **PostgreSQL** as the primary datastore.
- **Docker / docker-compose** for all environments (dev, sandbox, prod).
- **Redis** for queues (off-session notifications run on a queue worker).
- **Africa's Talking** as the USSD aggregator and SMS provider.
- **Real-time guard tablet**: Laravel Reverb (or Pusher-compatible
  websockets). SMS fallback to the guard's phone where the tablet has no
  reliable internet.
- **Admin/tenant/manager dashboards**: prefer **Filament** for speed — it
  gives multi-tenant-aware CRUD, auth, and exports out of the box. Confirm
  before pulling it in; plain Blade + controllers is the fallback if Filament
  adds friction.
- **Tests**: Pest.

## Multi-tenancy model (single database, row-scoped)

Use **one PostgreSQL database** with **row-level tenant scoping** (a
`tenant_id` / `building_id` foreign key on scoped tables), NOT database-per-tenant.
Simpler to operate for the MVP.

- Enforce scoping with Eloquent **global scopes** + **policies**, so a tenant
  user can never read another tenant's visits even by guessing IDs.
- Building managers see all tenants within their building only.
- A super/platform admin (us) can see across buildings.

### Core entities (starting point — refine in the migration step)
- `buildings` — name, address, manager contact.
- `tenants` — belongs to a building; has a **unique routing code**, notification
  settings (guard phone, tenant contact, channels).
- `guards` — belong to a building; phone number, optional device/push token.
- `visits` — the check-in record: `building_id`, `tenant_id`, `visitor_phone`,
  `purpose`, `status` (`checked_in` / `checked_out` / `auto_closed`),
  `checked_in_at`, `checked_out_at`, `ussd_session_id`.
- `users` — building managers and tenant admins, with roles.

Routing code uniqueness: decide whether codes are globally unique or unique
**per building**. Per-building is more scalable (short codes, reused across
buildings) but requires the visitor to first identify the building. Because
Phase 1 is constrained to two screens, default to **globally unique tenant
codes** for now and leave a note in the migration. Flag this for Arthur to
confirm with the client (relates to Q1/Q4).

## USSD flow — Africa's Talking (VERIFY against official docs)

> Steven is fetching the official AT API docs (action item #1). Treat the
> mechanics below as the standard AT convention to validate, not as confirmed
> fact. If the docs differ, update this section.

**Callback:** AT sends an HTTP `POST` to our callback URL on each user step,
with fields roughly: `sessionId`, `phoneNumber`, `serviceCode`, `networkCode`,
and `text` (the accumulated user input, with each step joined by `*`).

**Response:** we reply with **plain text**:
- Prefix `CON ` → keep the session open, show the next screen.
- Prefix `END ` → close the session, show a final message.

**The two-screen flow (Phase 1):**
1. Session start (`text` empty) → `CON Enter the code of who you are visiting:`
2. Visitor enters tenant routing code → resolve it. If valid →
   `CON You are visiting {Tenant}. Reason: 1. Meeting 2. Delivery 3. Interview 4. Other`
   If invalid → `END Code not recognised. Please check the posted list.`
3. Visitor picks a purpose → **persist the visit**, **dispatch the notification
   job to the queue**, then immediately return
   `END Thank you. Your arrival has been logged.`

**Hard rules for the USSD controller:**
- The visitor's phone number comes from the session payload — never ask for it.
- Do **all** notification work **off-session** on a queued job. The controller
  must persist + dispatch + return `END` fast. No SMS sends, no websocket
  pushes, no external HTTP inside the request that builds the USSD response.
  USSD sessions are time-limited and billed per time block; a slow handler
  causes timeouts and dropped check-ins.
- Sessions are stateless across POSTs except for what's in `text` — derive
  current step from `text`, do not assume in-memory state.
- Always respond `200 OK` with a `CON`/`END` body even on errors the user
  caused; reserve non-200 for genuine server faults.

## Off-session notification job

On a successful check-in, queue a job that:
1. Sends an **SMS** (via Africa's Talking) to the guard and/or tenant contact.
2. Pushes a **real-time event** to the guard tablet over Reverb/websockets.
3. Is **idempotent** on `ussd_session_id` so a retried job never double-notifies.

The guard tablet shows: visitor phone number, destination tenant, arrival time.
(Registered name is Phase 2 — do not display or imply it now.)

## Privacy & data protection (Uganda DPA) — design constraints

- Visitor phone numbers are **PII**. Never expose one tenant's visit data to
  another tenant (the row-scoping above enforces this).
- The whole point vs. the paper book is that the **next visitor can't read the
  previous one's details** — keep it that way in every UI.
- Production data must be hosted on **Uganda-localised infrastructure**
  (e.g. Raxio). The US cloud sandbox is **dev/testing only** and must not hold
  real visitor data. Keep secrets and DB config environment-driven so the
  same containers redeploy onto local infra unchanged.
- Make a visit-log **retention window** configurable rather than keeping data
  forever.

## Local development & testing

- `docker compose up` should bring up: app (php-fpm), nginx, postgres, redis,
  the queue worker, and reverb.
- Provide a `Makefile` or composer scripts for the common loops:
  migrate/seed, run Pest, tinker.
- Test the USSD flow **without a real telco** using the Africa's Talking USSD
  **simulator** (and/or a local script that POSTs the AT-shaped payload to the
  callback). This is separate from, and cheaper than, the live shared-code
  session costs.
- Seed at least: one building, two tenants with distinct codes, one guard.

## Conventions

- Migrations: one concern per migration, descriptive names.
- Keep the USSD state machine in a dedicated, testable service class — not
  inline in the controller — so screens are easy to add/verify.
- Every new scoped table gets its global scope + policy in the same PR.
- Write a Pest test for each USSD screen transition (valid code, invalid code,
  purpose selection, session resume mid-flow).

## Open questions to resolve before/while building (owners noted)

- **Q1 / per-building price** and **Q4 / tenant scoping details** — Arthur to
  confirm with client. Affects routing-code uniqueness decision above.
- **Q2 / session pricing** — Steven pushing for flat per-session pricing with
  the aggregator. Does not block the build; affects cost model only.
- **Q5 courier flow / Q6 checkpoint mapping** — Arthur confirming. NOT in the
  two-screen check-in MVP; do not build until specced.

## People

- **Steven** — business/commercial, aggregator docs + sandbox access, telco
  negotiation, data-protection registration. Not the code owner.
- **Arthur** — client-side confirmations (price, courier, checkpoints).
- **Team** (the.seeker.47, Elroy, and others) — the build. First-time USSD
  integrators — favour clarity and comments around the USSD layer.
