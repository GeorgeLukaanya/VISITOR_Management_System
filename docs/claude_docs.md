# Docs Map — Visitor Management (Phase 1)

> The fast version. One screen, no ceremony. Read this first, then jump to the
> deep doc only when you need the *why*. Team of 3, moving fast — keep it lean.

## What we're building (one line)
USSD visitor check-in that replaces the paper book: dial a tenant code → pick a
purpose → arrival logged → tenant/guard notified off-session. Two screens, hard stop.

## Where everything lives

| Need | Go to |
|---|---|
| Full architecture (arc42) | [architecture_overview.md](architecture_overview.md) |
| Why we chose X | [adrs/](adrs/) — decision log |
| Open trade-offs / unknowns | [considerations.md](considerations.md) |
| Current risks | [risk_register.md](risk_register.md) |
| Routing-code algorithm | [design_notes/routing_code_generation.md](design_notes/routing_code_generation.md) |
| C4 diagrams + ERD | [diagrams/](diagrams/) |
| Build guardrails (Claude reads this) | [../CLAUDE.md](../CLAUDE.md) |
| Run it locally | [../RUNNING.md](../RUNNING.md) |

## The 8 facts that shape every decision

1. **Two screens, no more.** Code entry → purpose pick → `END`. Adding a screen costs money and timeout risk.
2. **Nothing slow on-session.** Persist + dispatch + return `END`. No SMS, no websocket, no external HTTP inside the USSD request.
3. **Notifications are off-session** on a queue worker (separate container — ADR-005). Idempotent on `ussd_session_id`.
4. **Single DB, `tenant_id`-scoped rows** (ADR-002). Global scope + policy on every scoped table, same PR.
5. **No cross-tenant reads.** Manager building-wide view is the *only* all-tenant path — separate, audited code.
6. **Visitor phone comes from the session payload** — never ask for it. It's PII; encrypt at rest + in transit.
7. **Check-in record is source of truth.** Notifications are best-effort on top — never duplicate or lose a check-in.
8. **Stack is locked** (ADR-001): Laravel + Postgres + Docker + Redis + Africa's Talking. Pest for tests.

## The critical path (USSD check-in)
```
dial code → AT session → screen 1 (enter code) → screen 2 (pick purpose)
   → persist visit → dispatch queue job → END "logged"   [must be fast]
        └─ off-session: SMS + Reverb push to guard/tenant  [idempotent]
```

## Quality goals, ranked
Availability > Security > Usability (feature phones, 2G) > Modifiability > Scalability.
Performance = a latency *budget* feeding availability (each screen returns in low single-digit seconds), not a throughput goal.

## Decisions at a glance (full text in `adrs/`)
- **001** Stack: Laravel + Postgres + Docker
- **002** Multi-tenancy: `tenant_id` column, single DB
- **003** Notifications: SSE + PWA push, optional WhatsApp per tenant
- **004** CQRS for routing codes
- **005** Queue worker = separate container

## Open questions (don't block the build)
- Routing codes globally unique vs per-building (default: global) — Arthur to confirm.
- Session-drop recovery: fresh session or resume on redial — undecided.
- Hosting region / data residency — pending regulatory confirmation.

---
*Living doc. If a fact here goes stale, fix it here and in the source doc — don't let them drift.*
