# Running the app — defaults & expected outcomes

A companion to the README's [Quick start](README.md#quick-start). This document
gives you (1) the **default values** seeded/configured for local dev so you can
log in and test immediately, and (2) the **expected outcome of each step** so you
can tell whether the stack came up correctly.

> All values below are **dev/demo defaults only**. Per the Uganda DPA constraints
> (see `CLAUDE.md`), the sandbox must never hold real visitor data, and these
> credentials must never reach production.

---

## Default values

### Dashboard logins

All seeded users share the password **`password`**. Open
<http://localhost:8088/login>.

| Role             | Email                   | Sees                                      |
|------------------|-------------------------|-------------------------------------------|
| Platform admin   | `admin@example.com`     | All buildings (super admin — that's us)   |
| Building manager | `manager@example.com`   | Everything in **Crested Towers** only     |
| Tenant admin     | `acme@example.com`      | **Acme Bank** visits only                 |
| Tenant admin     | `umbrella@example.com`  | **Umbrella Legal** visits only            |

Use these to verify multi-tenant scoping by hand: log in as `acme@…` and confirm
you cannot see Umbrella's visits, and vice versa.

### Seeded demo data

| Entity      | Value                                                      |
|-------------|------------------------------------------------------------|
| Building    | Crested Towers — Hannington Road, Kampala                  |
| Manager     | Building Manager — `+256700000100`                         |
| Tenant 1    | Acme Bank — **routing code `1001`** — `+256700000201`      |
| Tenant 2    | Umbrella Legal — **routing code `1002`** — `+256700000202` |
| Guard       | Front Gate Guard — `+256700000300`                         |

### USSD flow values

| Thing                     | Value                                              |
|---------------------------|----------------------------------------------------|
| Callback endpoint         | `POST /ussd`                                        |
| Tenant routing codes      | `1001` (Acme), `1002` (Umbrella)                    |
| Purpose menu (screen 2)   | `1. Meeting  2. Delivery  3. Interview  4. Other`   |
| Default simulator phone   | `+256700000001`                                     |
| Default service code      | `*384*1234#`                                        |
| Example check-in input    | `1001*1` → Acme Bank, purpose Meeting               |

### Ports & service hosts

Published **host** ports come from the root `.env`; **internal** hostnames are the
compose service names that `src/.env` already targets.

| Service          | Host (browser/tools)      | Internal (containers)  |
|------------------|---------------------------|------------------------|
| App (nginx)      | <http://localhost:8088>   | `nginx` / `app:9000`   |
| Postgres         | `127.0.0.1:55432`         | `postgres:5432`        |
| Redis            | `127.0.0.1:56379`         | `redis:6379`           |
| Reverb (ws)      | `127.0.0.1:8081`          | `reverb:8080`          |

### Database credentials

| Key       | Value                |
|-----------|----------------------|
| Database  | `visitor_management` |
| Username  | `vms`                |
| Password  | `secret`             |

---

## Expected outcome of each step

Walk through these in order. The "Expected" line tells you what a healthy run
looks like; if you see something else, check [When it goes wrong](#when-it-goes-wrong).

### Step 0 — prerequisites

```bash
docker --version
docker compose version
```

**Expected:** both print a version. Compose must be **v2** (`docker compose`, two
words), not the legacy `docker-compose`.

### Step 1 — env file

```bash
cp -n src/.env.example src/.env
```

**Expected:** no output. `src/.env` now exists. (`-n` means an existing file is
left untouched — that's fine.)

### Step 2 — build

```bash
make build
```

**Expected:** ends with `... Built` lines for `app`, `queue`, and `reverb`. First
build takes a few minutes; later builds are cached and quick.

### Step 3 — start

```bash
make up
docker compose ps
```

**Expected:** `make up` prints `App should be at http://localhost:8088`.
`docker compose ps` lists **six** containers; `vms_postgres` shows `(healthy)` and
the rest show `Up`:

```
vms_app        Up
vms_nginx      Up        0.0.0.0:8088->80/tcp
vms_postgres   Up (healthy)  0.0.0.0:55432->5432/tcp
vms_queue      Up
vms_redis      Up        0.0.0.0:56379->6379/tcp
vms_reverb     Up        0.0.0.0:8081->8080/tcp
```

### Step 4 — app key (first run only)

```bash
docker compose exec app php artisan key:generate
```

**Expected:** `INFO  Application key set successfully.` You can skip this if
`APP_KEY=` in `src/.env` is already populated (the example ships a dev key).

### Step 5 — migrate & seed

```bash
make migrate
make seed
```

**Expected (migrate):** a list of migrations ending in `DONE`, or
`INFO  Nothing to migrate.` if the schema already exists.

**Expected (seed):**

```
Seeded: 1 building, 2 tenants (codes 1001/1002), 1 guard, 4 users.
Tenant codes — Acme Bank: 1001, Umbrella Legal: 1002
```

The seeder is **idempotent** — re-running `make seed` is safe and prints the same
lines without error.

### Step 6 — verify the web app

```bash
curl -I http://localhost:8088/login
```

**Expected:** `HTTP/1.1 200 OK`. In a browser, <http://localhost:8088/login> shows
the login form; sign in with `acme@example.com` / `password`.

### Step 7 — verify the USSD check-in

```bash
docker compose exec app php artisan ussd:simulate --text="1001*1"
```

**Expected:** the final screen is

```
END Thank you. Your arrival has been logged.
```

A new visit row now exists for Acme Bank. Run the simulator with no `--text` for
the interactive, screen-by-screen handset emulation. Watch the off-session job
fire with `docker compose logs -f queue` in another terminal.

---

## When it goes wrong

| Symptom                                              | Likely cause / fix                                                                 |
|------------------------------------------------------|------------------------------------------------------------------------------------|
| `port is already allocated` on `make up`             | Another service holds the port. Change `APP_PORT`/`*_FORWARD_PORT` in the root `.env`, then `make down && make up`. |
| `make up` prints port 8080, not 8088                 | Old Makefile. Pull the latest — it now reads `APP_PORT` from the root `.env`.       |
| `duplicate key ... routing_code` on `make seed`      | Old seeder. The current seeder is idempotent; otherwise run `make fresh` to reset. |
| `vms_postgres` not `(healthy)`                       | Give it a few seconds, then `docker compose ps` again. Check `docker compose logs postgres`. |
| Login fails for a seeded user                        | Run `make seed` (or `make fresh`). Password is `password`.                          |
| `END Code not recognised` from the simulator         | Use a seeded routing code: `1001` or `1002`.                                        |
| Want a totally clean slate                            | `make fresh` (drop + re-migrate + seed), or `docker compose down -v` to also delete the DB volume. |

---

## Handy commands

```bash
make logs                       # tail all container logs
docker compose logs -f queue    # follow the off-session notification worker
make tinker                     # Laravel REPL inside the app container
make fresh                      # wipe DB, re-migrate, re-seed
make down                       # stop the stack (DB volume kept)
docker compose down -v          # stop AND delete the DB volume (full reset)
```
