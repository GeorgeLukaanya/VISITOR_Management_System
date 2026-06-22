# ADR-004: CQRS read model  routing_codes table

**Status**: Accepted
**Date**: Phase 1

## Context
The routing code lookup (visitor types code → system resolves tenant) is the hottest read
path in the system  every single check-in goes through it, and its latency directly
determines whether the USSD session times out or succeeds.

The normalized write path would require joining organizations → buildings → tenants to
resolve a code. That join chain on the critical session path is an unacceptable latency
risk.

## Decision
Apply a CQRS read model to the `routing_codes` table. The table is denormalized  it stores
`org_id`, `building_id`, `tenant_id`, and `display_name` (copied from `tenants.name`)
alongside the code itself. The lookup query becomes a single indexed exact-match on one
table with no joins.

A partial unique index enforces that no two active codes collide:
```sql
CREATE UNIQUE INDEX idx_routing_codes_active
  ON routing_codes (code)
  WHERE status = 'active';
```

## Consequences
- Lookup is a single index scan  sub-millisecond regardless of total table size.
- `display_name` must be kept in sync when `tenants.name` changes  two writes instead of
  one. Accepted: tenant name changes are rare admin operations; check-ins are constant.
- Retired codes are preserved as history (status = 'retired') without polluting the active
  index  the partial index only tracks live codes.