# Risk Register — Visitor Management SaaS

Status: Living document. Review cadence: every 1–2 weeks, or whenever a new risk surfaces.

| ID | Risk | Likelihood | Impact | Owner | Mitigation / Status |
|---|---|---|---|---|---|
| R1 | Single USSD aggregator dependency — if Africa's Talking has an outage or throttles us, the entire visitor check-in flow is unreachable | Medium | Critical | Steven | **Accepted for Phase 1.** Revisit multi-aggregator fallback after MVP is live and stable. |
| R2 | USSD session timeout exceeded due to slow backend response (DB query, notification dispatch blocking the session) | Medium | Critical | Team | Off-session notification dispatch (architecture decision); needs load testing against sandbox before launch. |
| R3 | Cross-tenant data leak via a missed `tenant_id` filter in a query or export path | Medium | Critical | Team | App-layer discipline now; consider Postgres RLS as defense-in-depth post-prototype (see ADR-002). |
| R4 | Data residency / regulatory requirement discovered late, forcing a hosting region change | Low–Medium | High | Arthur | Open question — confirm applicable data protection requirements per deployment market before Phase 1 deployment, not after. |
| R5 | Session drop (visitor-side network issue) causes duplicate or corrupted visitor log entries | Medium | Medium | Team | Needs explicit redial/recovery design — currently an open question (architecture overview §6.2). |
| R6 | Per-session USSD cost pressure leads to cutting validation/UX corners in the two-screen flow | Medium | Medium | Steven / Team | Track via Q2 pricing negotiation outcome; revisit flow design once flat pricing is confirmed (or not). |
| R7 | No dedicated security/ops person on a 4-person team — security review and incident response are ad hoc | Medium | High | Team | Accepted constraint for Phase 1; minimum viable observability (architecture overview §8.5) is the mitigation, not a substitute. |
| R8 | Phase 2 dashboards tightly couple to Phase 1's database shape, making later changes expensive | Low (now) / Medium (later) | Medium | Team | Open decision in ADR-002 — direct DB access vs API layer for Phase 2 consumers. Revisit before Phase 2 starts. |
| R9 | Checkpoint mapping (Q6) and courier flow (Q5) not yet confirmed with client, may affect data model assumptions already being built | Medium | Medium | Arthur | Confirm with client (Phase 1 Action #3) before the data model is finalized. |

## How to use this
- Add new rows as risks surface — don't wait for a "proper" review meeting.
- "Accepted" status is a legitimate outcome, not a failure to mitigate — just say so
  explicitly so it isn't silently forgotten.
- Anything still "Open question" after Phase 1 prototype (Action #5) should be escalated,
  not carried forward indefinitely.