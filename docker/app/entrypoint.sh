#!/usr/bin/env bash
# MPC e-Tender — app container entrypoint.
#
# 1. Wait for mysql to accept connections (compose's depends_on health
#    gate handles this in most cases, but a 30s safety loop here covers
#    edge cases like mysql restarting after volume reset).
# 2. Optionally run `php artisan migrate --force` when RUN_MIGRATIONS=true.
#    Set per-service in compose.yaml: `app` defaults to false, `make
#    migrate` flips it on demand. Keeping migrations OUT of the default
#    startup loop prevents accidental schema churn during ordinary
#    `make up`.
# 3. Forward to the actual command (Sail's start-container for the app
#    service, `php artisan horizon` for the worker).
#
# Exits non-zero on mysql timeout so docker compose surfaces the failure
# rather than restart-looping silently.

set -euo pipefail

mysql_wait_timeout="${MYSQL_WAIT_TIMEOUT:-30}"
elapsed=0

echo "[entrypoint] waiting up to ${mysql_wait_timeout}s for mysql:3306…"
while ! (echo > /dev/tcp/mysql/3306) 2>/dev/null; do
  sleep 1
  elapsed=$((elapsed + 1))
  if [ "$elapsed" -ge "$mysql_wait_timeout" ]; then
    echo "[entrypoint] mysql:3306 not reachable after ${mysql_wait_timeout}s — aborting" >&2
    exit 1
  fi
done
echo "[entrypoint] mysql is reachable (${elapsed}s)"

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] RUN_MIGRATIONS=true — running php artisan migrate --force"
  php /var/www/html/artisan migrate --force --no-interaction
fi

# Forward to whatever `command:` was passed (start-container for app,
# `php artisan horizon` for app-horizon). Use exec so PID 1 is the
# real process — signals propagate cleanly.
echo "[entrypoint] exec: $*"
exec "$@"
