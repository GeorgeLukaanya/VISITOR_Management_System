# Considerations USSD Two-Screen Check-in Flow

Status: Draft, under discussion. Not yet finalized — once agreed, this becomes the basis for
`design-notes/ussd-two-screen-flow.md` and updates §6 of `architecture-overview.md`.

---

## Agreed so far

- The flow is **two screens of input**, typed (not baked into the dial string):
  1. **Screen 1** — visitor types the building/tenant code.
  2. **Screen 2** — system resolves the code, shows the tenant name back to the visitor as
     implicit confirmation, combined with the prompt for reason of visit. Visitor types the
     reason.
- **Identity is implicit**, not a typed input — the aggregator passes the visitor's MSISDN
  (phone number) with the session automatically. No screen is spent on "who are you."
- Confirmation of the *destination* (right tenant/building) is folded into screen 2 rather
  than costing a separate screen — the visitor sees the tenant name before/while entering
  the reason, so a wrong code is caught before submission instead of surfacing later as a
  guard confusion or a no-show notification.
- Session ends immediately after screen 2 submission; notification dispatch to
  tenant/guard happens **asynchronously, off-session** — never blocks or delays session
  close.
- Invalid/unrecognized code ends the session with an explicit error message rather than
  failing silently.

## Sequence diagram (current draft — sequencediagram.org syntax)

```
title USSD Check-in — Two Screen Flow (typed code)

Visitor->Aggregator:Dials shortcode
Aggregator->System:Session start (MSISDN included)
System-->Aggregator:Screen 1 — "Enter building/tenant code:"
Aggregator-->Visitor:Display screen 1
Visitor->Aggregator:Types code
Aggregator->System:Screen 1 response (code)
System->DB:Lookup tenant by code

alt code found
    DB-->System:Tenant found
    System-->Aggregator:Screen 2 — "Checking in at: [Tenant].\nEnter reason for visit:"
    Aggregator-->Visitor:Display screen 2
    Visitor->Aggregator:Types reason
    Aggregator->System:Screen 2 response (reason)
    System->DB:Write check-in (MSISDN, tenant_id, reason, timestamp)
    DB-->System:Ack
    System-->Aggregator:End session — "Checked in, [tenant] notified"
    Aggregator-->Visitor:Confirmation message
    System->Dispatcher:Dispatch notification (async)
    Dispatcher->Guard:Real-time arrival alert
else code not found
    DB-->System:No match
    System-->Aggregator:End session — "Code not recognized,\nplease try again"
    Aggregator-->Visitor:Error message
end
```

---

## Open questions — need a decision

1. **Invalid code retry behavior** — does the visitor have to redial from scratch after a
   bad code, or do we re-prompt screen 1 within the same session? Redial-from-scratch is
   simpler to build and cheaper per the current session-cost negotiation (§2.3 of
   architecture overview); in-session retry is friendlier UX but means paying for a longer
   session and adds a retry-count edge case (what happens after N failed attempts — do we
   cap it?).
2. **Reason input — free text or selection menu?** Still open. Free text is flexible but
   slower to type and harder to route/report on downstream (ties into Q6 checkpoint
   mapping). A numbered menu (e.g. "1. Delivery 2. Meeting 3. Interview...") is faster and
   more structured for tenant logs/exports, but the category list needs to be agreed with
   the client first, and may not be flexible enough for unusual visit reasons.
3. **Session-drop recovery** — if the session drops between screen 1 and screen 2 (visitor's
   network issue), does redialing start a brand-new session, or do we attempt to detect and
   resume the prior attempt? Carried over from risk register R5 — still unresolved.
4. **Code resolution scope** — is the code unique per tenant, or could the same code map to
   multiple tenants in different buildings (requiring disambiguation)? Assumed unique for
   now, but worth explicitly confirming since it affects the DB lookup logic in screen 2.
5. **Tenant name confirmation — what if it's ambiguous or generic?** E.g. if a tenant's
   display name is something non-distinctive, does showing it back actually help the visitor
   catch a wrong code, or should we also show the building name for extra confirmation
   (costs more characters, same screen, not an extra screen — but worth considering for
   session-cost reasons if billed per character/byte rather than per screen).

---

## Next step
Resolve open questions 1–5 above (ideally with Arthur/client input where they tie into Q5/Q6
of the original Phase 1 scope), then promote this into a finalized design note and update
the architecture overview's Runtime View section.