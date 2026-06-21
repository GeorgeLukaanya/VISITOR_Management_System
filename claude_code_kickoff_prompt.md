# Claude Code — Phase 1 Kickoff Prompt

> Paste this into Claude Code in a fresh, empty repo (with `CLAUDE.md` already
> placed at the root). It walks the build through the dependency chain in our
> action plan: **sandbox-shaped scaffolding → container → two-screen prototype
> → multi-tenant data model → Phase 1 build.** Do the steps in order and stop
> for review at the checkpoints. Don't build anything marked out-of-scope in
> CLAUDE.md.

---

You are bootstrapping the Phase 1 MVP described in `CLAUDE.md` (read it first
and follow it strictly). The scope is "Replace the Book" only. Work in the
order below. After each numbered step, summarise what you did and **pause for
my review** before continuing.

**Step 0 — Confirm understanding.**
Restate, in 5–8 bullets, the Phase 1 scope, the two-screen USSD flow, the
off-session notification rule, and the multi-tenancy approach from `CLAUDE.md`.
List any assumption you are making. Do not write code yet.

**Step 1 — Project + container skeleton (maps to action #4).**
Scaffold a fresh Laravel app and a `docker-compose.yml` bringing up: app
(php-fpm), nginx, PostgreSQL, Redis, a queue worker, and Reverb. Add a
`Makefile` (or composer scripts) for: `up`, `migrate`, `seed`, `test`,
`tinker`. Wire all config (DB, AT keys, Reverb) to environment variables with a
committed `.env.example`. Confirm `docker compose up` boots and the app
responds. **No business logic yet.**

**Step 2 — Multi-tenant data model (maps to action #6).**
Create migrations, models, factories, and seeders for: `buildings`, `tenants`
(with a unique routing code), `guards`, `visits`, and `users` with roles.
Implement row-level tenant scoping via Eloquent global scopes + policies so a
tenant can never read another tenant's visits. Seed one building, two tenants
with distinct codes, and one guard. Add Pest tests proving the scope holds
(tenant A cannot read tenant B's visits). Note the routing-code-uniqueness
decision as a comment for Arthur to confirm.

**Step 3 — USSD two-screen flow against a simulated aggregator (maps to
actions #1 & #5).**
We do not yet have live aggregator access, so build to the standard Africa's
Talking convention documented in `CLAUDE.md` and make it swappable:
- A `POST` USSD callback route.
- A dedicated, testable `UssdSessionService` (state machine) — not logic in the
  controller — implementing exactly: screen 1 (enter tenant code) → screen 2
  (resolve code, pick purpose) → `END` + persist visit + dispatch notification
  job. Invalid code → `END` with a clear message.
- The controller persists the visit, dispatches the queued notification job,
  and returns the `END` body **fast** — no third-party calls in the request.
- A local helper (artisan command or script) that POSTs AT-shaped payloads
  (`sessionId`, `phoneNumber`, `serviceCode`, `text`) so we can drive the full
  flow without a telco, plus notes on pointing it at the AT USSD simulator once
  Steven provides sandbox access.
- Pest tests for every transition: empty start, valid code, invalid code,
  purpose selection, and a mid-flow resume (same `sessionId`, accumulated
  `text`).

**Step 4 — Off-session notification job.**
A queued job that, on check-in: sends an SMS via Africa's Talking to the
guard/tenant, and broadcasts a Reverb event to the guard tablet with visitor
phone + tenant + arrival time. Make it **idempotent on `ussd_session_id`**.
Stub the AT SMS client behind an interface so tests run without network and we
can drop in real credentials later. Test: one check-in → exactly one SMS
attempt + one broadcast, and a retried job does not double-send.

**Step 5 — Dashboards + exports (maps to remaining Phase 1 items).**
Tenant view: a tenant sees only their own incoming/recent visits and can export
their own logs to CSV. Building-manager view: building-wide visit logs with CSV
export. Use Filament if it speeds this up (confirm with me first per
`CLAUDE.md`); otherwise Blade. Enforce scoping through the same policies from
Step 2. Add a feature test that a tenant export contains only that tenant's
rows.

**Step 6 — Wrap-up.**
Produce a short `README.md`: how to run, how to drive the USSD flow locally,
how to run tests, and a checklist of what still needs the **real** aggregator
credentials/sandbox before live testing. List anything you stubbed or assumed
that needs a human decision.

### Guardrails (from CLAUDE.md — repeating because they matter)
- Two USSD screens maximum. No identity/NIRA verification (Phase 2). No courier
  or checkpoint flows (not specced).
- Never ask the visitor for their phone number — take it from the session.
- All notification/external work is off-session on the queue. Keep the USSD
  request handler fast.
- Treat the AT mechanics as "verify against official docs" — keep the
  aggregator integration behind an interface so swapping in confirmed details
  is a small change.
- Production data is Uganda-localised; the sandbox holds no real visitor data.
