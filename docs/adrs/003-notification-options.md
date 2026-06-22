# ADR-003: Real-time notification delivery SSE + PWA push, optional WhatsApp per tenant

**Status**: Accepted (supersedes initial draft SMS and email dropped)
**Date**: Phase 1

## Context
When a visitor completes the USSD check-in flow, the relevant tenant and/or security guard
must be notified in real time. The notification must be dispatched asynchronously —
off-session so it never blocks or delays the visitor's USSD session close (see
architecture overview §6.1, §8.3).

Two concerns need separate decisions:
1. How does the server push the notification to the guard/tenant dashboard (transport)?
2. What is the fallback when the guard is not at a screen?

## Decision

**Phase 1 in-browser transport: Server-Sent Events (SSE)**
SSE pushes visit notifications from the Laravel backend to the guard/tenant's open browser
tab. One-directional, no extra infrastructure, built into every browser natively.

**Off-browser: PWA push notifications**
The guard dashboard is built as a Progressive Web App. After a one-time "Add to Home
Screen" install, Web Push delivers notifications to the guard's phone even when the browser
tab is closed behaves identically to a native app notification. Uses VAPID keys, no
third-party push service required, zero per-notification cost.

**Optional paid channel: WhatsApp Business API (per-tenant opt-in)**
Tenants who want WhatsApp notifications for their own staff can enable it and absorb the
per-message cost themselves it is not a platform-wide cost. Not enabled by default.
Integration via Africa's Talking (already in the AT ecosystem). Gated behind a tenant-level
feature flag in the dashboard.

**Interface design: channel-agnostic from day one**
The notification dispatch layer is a single interface with multiple concrete implementations
(SSE, PWA push, WhatsApp). Adding a new channel Telegram, Reverb WebSockets, anything
else only requires a new implementation behind the interface. Visit creation logic and
the guard UI are never touched when a channel changes.

## Alternatives considered
- **SMS** dropped. Per-message cost with no clear owner (platform absorbs it or tenant
  does?) and Africa's Talking SMS adds a second billing line to manage. PWA push covers
  the same "guard off-browser" scenario for free.
- **Email** dropped. Not real-time enough for reception use. Guards will not have push
  email notifications on in a way that matches the urgency of a visitor arriving.
- **WebRTC** built for peer-to-peer media (audio/video). STUN/TURN/ICE overhead is
  entirely disproportionate for a one-directional visit notification. Rejected.
- **WebSockets via Laravel Reverb** full duplex, better long-term fit if the guard
  dashboard eventually needs two-way interaction (guard acknowledges visitor, flags an
  issue). SSE is sufficient for Phase 1's one-directional push; Reverb is the natural
  Phase 2 upgrade path and fits cleanly behind the channel-agnostic interface.
- **WebSockets via Pusher** managed but per-message cost and external dependency.
  Rejected for Phase 1.
- **Telegram Bot** free and technically clean but less universal than WhatsApp in most
  target markets. Not a priority; can be added as a channel implementation later if demand
  surfaces.
- **In-app polling** wasteful, latency proportional to poll interval. Rejected.

## Consequences
- SSE requires no additional package in Laravel streamed HTTP response, works out of the
  box.
- PWA push requires a VAPID key pair (generated once) and a service worker in the
  dashboard frontend. One-time setup cost, no ongoing infrastructure or billing.
- PWA push on iOS requires iOS 16.4+ (Safari) guards on older iPhones will not receive
  off-browser notifications. Accepted risk for Phase 1; SSE in-browser still works for
  them.
- WhatsApp channel is entirely opt-in and tenant-funded zero platform cost unless we
  choose to subsidize it later. Gated by a tenant feature flag so it has no impact on
  tenants that don't enable it.
- The channel-agnostic interface directly protects the Modifiability quality attribute
  (architecture overview §10) Phase 2 can introduce WebSockets or any new channel
  without touching visit creation or the guard UI.
- Risk: guard has not done the one-time PWA install off-browser push does not work for
  them. Mitigated by SSE for the in-browser case and by the onboarding flow prompting the
  install explicitly. Added to risk register.