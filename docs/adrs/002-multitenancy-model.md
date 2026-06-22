# ADR-002: Use Multi-tenancy via tenant_id column (not schema-per-tenant)

**Status**: Accepted
**Date**: Phase 1

## Context
The product is inherently multi-tenant — multiple businesses (tenants) per building, each
needing isolated visitor data, plus a building-manager view that spans all tenants in a
building. We have no dedicated DBA and a 1-month timeline.

## Decision
Use a **single Postgres database with `tenant_id`-scoped rows**, rather than
schema-per-tenant or database-per-tenant isolation.

## Alternatives considered
- **Schema-per-tenant** — stronger isolation guarantee, but migration/operational complexity
  (running migrations across N schemas, connection pooling per schema) is significant
  overhead for a 4-person team to maintain correctly under time pressure.
- **Database-per-tenant** — strongest isolation, but operationally heaviest; explicitly
  rejected as disproportionate to current scale (handful of buildings, not hundreds).
- **Single table, no tenant scoping, app-layer filtering only** — rejected as too fragile;
  a single missed `WHERE tenant_id = ?` clause becomes a data leak (see Crosscutting
  Concepts §8.1 in architecture overview).

## Consequences
- Simpler to operate and migrate with a small team — one schema, one migration path.
- Isolation correctness now depends on **disciplined query scoping** rather than database-
  enforced boundaries. Mitigation: consider Postgres row-level security (RLS) policies as a
  defense-in-depth layer once the data model stabilizes post-prototype, rather than relying
  solely on application code discipline.
- Building-manager "all tenants" export must be treated as a distinct, explicitly-audited
  query path — not just a relaxed version of the tenant-scoped path — to avoid this becoming
  the accidental leak point.
- **Open question carried into Phase 2 planning**: when dashboards become a second consumer
  of this database, do they connect directly to Postgres (reusing the same tenant-scoping
  discipline) or go through an API layer? Not decided yet — direct DB access is faster to
  ship now but increases coupling risk later (see architecture overview §1.3, §10
  Modifiability). Revisit before Phase 2 kicks off.