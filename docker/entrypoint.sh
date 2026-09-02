#!/bin/sh
# Runs once every time the container starts, BEFORE the web server.
# `set -e` = abort immediately if any command fails.
set -e

# --- 1. Listen on the port Render gives us -------------------------------
# Render injects $PORT (usually 10000). Locally there's none, so default.
: "${PORT:=8080}"
sed -ri "s!^Listen 80\$!Listen ${PORT}!" /etc/apache2/ports.conf
sed -ri "s!\*:80!*:${PORT}!" /etc/apache2/sites-available/000-default.conf

# --- 2. Safety net for APP_KEY -----------------------------------------
# You should set a real APP_KEY in Render's env vars. If it's missing we
# generate a throwaway one so the app can at least boot (sessions/old
# encrypted data won't survive a restart though).
if [ -z "${APP_KEY:-}" ]; then
    echo "entrypoint: APP_KEY not set - generating a temporary one"
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

# --- 3. Warm Laravel's caches with the real env now present -----------
# config + view caching are safe. route:cache is skipped on purpose:
# routes/web.php has a closure route ("/") which PHP can't serialise.
php artisan config:cache
php artisan view:cache
php artisan event:cache || true

# --- 4. Apply database migrations to Neon ----------------------------
# --force = don't prompt "are you sure?" (there's no TTY in a container).
php artisan migrate --force

# --- 5. Make sure Laravel can write its runtime dirs -----------------
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# --- 6. Hand control to the CMD (apache2-foreground) ----------------
exec "$@"
