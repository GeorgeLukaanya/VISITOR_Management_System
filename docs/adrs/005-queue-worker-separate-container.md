# ADR-005: Queue worker runs as a separate container  same image, different command

**Status**: Accepted
**Date**: Phase 1

## Context
Notification dispatch (PWA push, WhatsApp, email) cannot run inline with the USSD callback
handler  a slow provider would exceed the aggregator's per-screen timeout and kill the
visitor's session. The web process must respond to the aggregator immediately after writing
the visit record.

## Decision
Run the Laravel queue worker as a **separate Docker container** using the same image as the
web container but with a different startup command:

| Container | Command | Role |
|---|---|---|
| `app` | `php-fpm` | HTTP - USSD callbacks, dashboard API |
| `worker` | `php artisan queue:work` | Async jobs  notifications, exports |

Queue driver: Laravel database queue (`jobs` table in Postgres). No additional
infrastructure (Redis etc.) required for Phase 1.

## Consequences
- Web container writes visit record, pushes job onto queue, responds to aggregator
  immediately  session latency budget protected.
- Worker container processes notifications off-session  a slow or failed notification
  never affects the check-in.
- Containers fail and restart independently  worker down means delayed notifications, not
  failed check-ins; app down means queued jobs process on restart.
- One Dockerfile maintained, not two.