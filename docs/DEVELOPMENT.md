# Local development

The MPC e-Tender Docker stack mirrors the Laravel Cloud production
environment (Laravel + MySQL 8 + Valkey + S3-compatible storage +
Mailpit + Horizon worker) so that wizard-flow bugs and other UX
regressions can be reproduced locally without deploy round-trips.

## Prerequisites

- **Docker Desktop** (Windows or macOS) with WSL2 backend enabled on
  Windows. Recent version recommended (29.x as of 2026-04).
- **Make** — not bundled with Windows. Install via Chocolatey:

  ```powershell
  choco install make
  ```

  All Makefile targets are thin wrappers around `docker compose`; if
  you can't or don't want to install Make, run the equivalent
  `docker compose ...` commands directly (see the Makefile for the
  exact mapping).
- **Git** with LF line endings (this repo's `.gitattributes` enforces
  this for shell scripts and YAML; if you're seeing weird parse errors
  on `.sh` files inside containers, check `git config core.autocrlf`).

## First-time setup

From the project root:

```bash
make build         # Build the app image (~5-10 min, one-time; pulls Sail's PHP 8.4 runtime)
make up            # Start all containers
make fresh         # Wipe volumes, run migrations, run core seeders (idempotent)
make seed-dev      # Populate dev fixtures (3 vendors, 7 tenders, sample PDF)
```

You should now be able to:

- Open <http://localhost:8000> and log in as `admin@mpc-group.com` / `password`
- Open <http://localhost:8900> (MinIO console, login `sail` / `password`) and see the `mpc-etender` bucket with the sample PDF
- Open <http://localhost:8025> (Mailpit web UI) — any mail Laravel sends will appear here

If `make up` succeeds but the app responds 500, run `make logs-app`
and look for the typical first-run errors:

- "no app key" → `make shell` then `php artisan key:generate`
- "table not found" → `make migrate`
- "S3 bucket does not exist" → `make mc-mb` (re-runs the MinIO bucket
  creation sidecar)

## Daily workflow

```bash
make up           # Bring the stack up (idempotent — starts what's not running)
make logs         # Tail logs from all services
make logs-app     # Just the Laravel app
make logs-horizon # Just the queue worker
make shell        # Bash shell inside the app container
make tinker       # `php artisan tinker` against the live MySQL
make pint         # Run code style fix
make down         # Stop the stack (volumes preserved)
```

For frontend work, start Vite in a separate terminal so it stays in
the foreground for log visibility:

```bash
make npm-dev      # Vite dev server with HMR
```

The HMR server is exposed at <http://localhost:5173>; the Laravel app
on `:8000` proxies to it via the `@vite` directive.

## Testing

```bash
make test          # Pest, sequential
make test-parallel # Pest, parallel (faster on multi-core)
```

**The test suite uses sqlite in-memory** (per `phpunit.xml`), not the
containerized MySQL. This is intentional — sqlite tests run in
~14 seconds vs. ~45 seconds against MySQL, and Laravel's SQL layer
has been validated enough for the cases tests cover. The Docker
MySQL is for **interactive** workflows: clicking around the wizard,
running `tinker`, etc.

If you specifically need a test against MySQL (e.g. testing a
column-type-specific bug), drop into a shell and override
`DB_CONNECTION` for the test run:

```bash
make shell
DB_CONNECTION=mysql DB_HOST=mysql DB_DATABASE=testing ./vendor/bin/pest tests/Feature/...
```

## The 7 seeded tenders

`DevDataSeeder` creates these reference numbers, each with a specific
bug-repro purpose:

| Reference | Type | Status | Purpose |
|---|---|---|---|
| `VIZ-T001` | Single-envelope, full BOQ | Published, deadline +7d | Healthy baseline for general smoke tests |
| `VIZ-T002` | Single-envelope, **empty BOQ** | Published | Repro for the future BUG-16 publish-guard. Category=HVAC (Fatima only) so Ahmed's view stays clean |
| `VIZ-T003` | Two-envelope, score 70, full BOQ | Published | BUG-15 repro; categories match Ahmed; pre-seeded with sample PDF on Ahmed's draft bid |
| `VIZ-T004` | Two-envelope, **no pass score** | Published | BUG-15 alternate path |
| `VIZ-T005` | Single-envelope, partial BOQ | Draft | Wizard editing repros |
| `VIZ-T006` | Single-envelope, was published | Cancelled | BUG-23 (addenda tab on cancelled) repro |
| `VIZ-T007` | Two-envelope, full BOQ | Published, with submitted + withdrawn bids | BUG-19 (withdraw → re-start unique constraint) repro |

## The 3 seeded vendors

| Email | Password | Company | Categories | Locale |
|---|---|---|---|---|
| `ahmed@al-rashid.iq` | `password` | Al-Rashid Construction Co. | Civil Works + MEP | en |
| `fatima@erbil-mep.iq` | `password` | Erbil MEP Solutions | MEP + HVAC | ar |
| `viz-vendor-3@test.local` | `password` | Throwaway Test Vendor | Civil Works | en (only used by VIZ-T007 fixture) |

The admin user `admin@mpc-group.com` / `password` is created by
`AdminUserSeeder` (called automatically by `make fresh`).

## What's running

```
$ make ps
NAME                    SERVICE        STATUS          PORTS
mpc-etender-app-1       app            Up (healthy)    0.0.0.0:8000->80, 5173->5173
mpc-etender-horizon-1   app-horizon    Up
mpc-etender-mysql-1     mysql          Up (healthy)    0.0.0.0:3306->3306
mpc-etender-valkey-1    valkey         Up (healthy)    0.0.0.0:6379->6379
mpc-etender-minio-1     minio          Up (healthy)    0.0.0.0:9000->9000, 8900->8900
mpc-etender-mailpit-1   mailpit        Up              0.0.0.0:1025->1025, 8025->8025
```

`meilisearch` is **not** in the default profile — it adds ~150 MB image
+ ~80 MB RAM and most dev work doesn't exercise Scout. Bring it up
when needed:

```bash
docker compose --profile search up -d meilisearch
```

## Known traps

- **No custom Dockerfile.** The `app` service builds from
  `vendor/laravel/sail/runtimes/8.4/Dockerfile` and our `php.ini` +
  `entrypoint.sh` are mounted via volumes. Pros: Sail's runtime is
  well-maintained and we get free updates; cons: ~1.2 GB image
  (heavier than a minimal alpine). If image size becomes a problem,
  swap to `php:8.4-fpm-alpine` and write a proper `docker/app/Dockerfile`.

- **Telescope and Pulse are off in dev** for parity with prod and with
  `phpunit.xml`'s `TELESCOPE_ENABLED=false`. Set both to `true` in
  `.env` if you want them enabled locally.

- **Tests use sqlite, not the containerized MySQL.** This is a
  deliberate speed trade-off; see "Testing" above.

- **Bind mount slowness on Windows.** If `make up` is slow because
  Composer/npm install over a Windows-side bind mount is glacial,
  consider cloning the repo inside WSL2 (`\\wsl$\Ubuntu\home\johnny\projects\`)
  and running `make` from inside WSL bash. The repo is on the Windows
  side by default — moving is your call.

- **Vite HMR through Docker.** Requires `usePolling: true` (already
  set in `vite.config.ts`). If HMR stops detecting your changes, check
  that the file you're editing is inside the `./resources/` tree
  (the bind mount root).

- **Reverb WebSocket port 8080.** If something else on your host is
  bound to 8080, reverb will fail to start. Either kill the conflict
  or change `REVERB_PORT` in your `.env` (and the
  `--port` arg in any `php artisan reverb:start` call).

## Troubleshooting

**Q: `make up` says "port 3306 already in use".**
A: You probably have local MySQL running (e.g. WAMP). Stop it, or
override the host port in `.env`: `FORWARD_DB_PORT=3307`, then connect
to `localhost:3307` from your host.

**Q: MinIO bucket missing — uploads return "NoSuchBucket".**
A: `make mc-mb` re-runs the bucket creation sidecar (idempotent).

**Q: Horizon isn't picking up jobs — the dashboard shows queues but
no completion.**
A: `make logs-horizon`. If you see "Connection refused" to valkey,
the worker started before valkey was healthy — `docker compose
restart app-horizon`.

**Q: `make fresh` fails on "key already exists".**
A: A previous run left orphan rows in a non-volume table. Run
`make down` (without `-v`), then `docker volume rm mpc-etender_mpc-mysql`
manually, then `make up && make fresh`.

**Q: I want to nuke everything and start over.**
A: `docker compose down -v && docker compose rm -f && rm .env`,
then `make build && make up && make fresh && make seed-dev`.

**Q: `php artisan migrate` fails with "Access denied for user".**
A: The `.env` `DB_PASSWORD` doesn't match what the MySQL container
was initialized with. The container reads it on first volume init
only. Either reset the volume (`docker volume rm mpc-etender_mpc-mysql`)
or update the user's password inside MySQL.

## Reference

- Compose file: `compose.yaml`
- Container init (waits for mysql, optionally migrates): `docker/app/entrypoint.sh`
- PHP overrides (upload limits, opcache): `docker/app/php.ini`
- Docker-stack env values: `.env.docker` (copied to `.env` on first `make up`)
- Build context excludes: `.dockerignore`
- Line ending policy: `.gitattributes`

For prod environment details, see `CLAUDE.md` and Laravel Cloud
secrets — the stack mirror is intentional.
