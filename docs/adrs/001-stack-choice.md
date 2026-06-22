# ADR-001: Use Laravel + PostgreSQL + Docker

**Status**: Accepted
**Date**: Phase 1

## Context
We need to ship a working MVP within roughly one month, with a team of 4 developers and no
dedicated DevOps/security specialist. The system must handle multi-tenant data, integrate
with a USSD aggregator over HTTP callbacks, and support real-time notifications.

## Decision
Use **Laravel (PHP) + PostgreSQL + Docker** as the core stack.

## Alternatives considered
- **Node.js/Express + Postgres** — comparable fit, but team has stronger existing Laravel
  experience, which matters more than language choice given the timeline.
- **Django + Postgres** — similar reasoning; rejected for the same team-familiarity reason.
- **Microservices from day one** — rejected outright: operational overhead (service
  discovery, distributed tracing, multiple deploy pipelines) is not affordable for a 4-person
  team with no dedicated ops, and Phase 1 scope doesn't need it.

## Consequences
- Fast initial velocity due to team familiarity; Laravel's batteries-included approach
  (queues, scheduling) directly supports the off-session notification requirement (§8.3 in
  architecture overview) without extra tooling.
- Docker gives us a consistent sandbox/dev/prod environment, important since the USSD
  aggregator integration needs a stable container for testing (Phase 1 Action #4).
- Postgres chosen over MySQL primarily for row-level security support, which is directly
  relevant to enforcing multi-tenant isolation (see ADR-002) if we choose to use it.
- Risk accepted: PHP/Laravel scaling patterns at high concurrency are well-understood
  industry-wide, so this is not considered a meaningful scalability risk at current
  expected load.