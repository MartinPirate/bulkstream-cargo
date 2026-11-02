#!/usr/bin/env bash
# Deploy the Bulkstream cargo demo to the shared Linode box.
#
#   deploy/.env.prod      compose secrets (DB passwords)        - gitignored
#   deploy/app.env.prod   Laravel .env for production            - gitignored
#
# Code syncs never touch the server's storage/ (logs, sessions).
set -euo pipefail

HOST="${HOST:-139.162.184.72}"
USER_="${DEPLOY_USER:-deploy}"
KEY="${SSH_KEY:-$HOME/.ssh/id_ed25519_linode}"
ROOT="${APP_ROOT:-/srv/bulkstream}"
COMPOSE="docker compose -f deploy/compose.prod.yml --env-file deploy/.env.prod"

LOG() { printf '\n\033[1;36m==>\033[0m %s\n' "$*"; }
DIE() { printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

cd "$(dirname "$0")/.."
[ -f deploy/.env.prod ] || DIE "deploy/.env.prod is missing"
[ -f deploy/app.env.prod ] || DIE "deploy/app.env.prod is missing"

remote() { ssh -i "$KEY" -o StrictHostKeyChecking=accept-new "$USER_@$HOST" "$@"; }
remote 'true' 2>/dev/null || DIE "Cannot SSH to $USER_@$HOST with $KEY"

remote "[ -d $ROOT ] || { sudo mkdir -p $ROOT && sudo chown $USER_:$USER_ $ROOT; }; mkdir -p $ROOT/app $ROOT/deploy"
first_deploy=$(remote "[ -f $ROOT/app/.seeded ] && echo no || echo yes")

LOG "Syncing code"
rsync -az --delete --no-perms --no-owner --no-group --omit-dir-times \
    --exclude '/.git/' --exclude '/vendor/' --exclude '/node_modules/' \
    --exclude '/.env' --exclude '/storage/' --exclude '/deploy/' \
    --exclude '/public/js/filament/' --exclude '/public/css/filament/' --exclude '/public/fonts/filament/' \
    --exclude '/bootstrap/cache/*.php' --exclude '/.seeded' \
    -e "ssh -i $KEY" ./ "$USER_@$HOST:$ROOT/app/"
rsync -az -e "ssh -i $KEY" deploy/Dockerfile deploy/compose.prod.yml deploy/nginx.conf "$USER_@$HOST:$ROOT/deploy/"
scp -q -i "$KEY" deploy/.env.prod "$USER_@$HOST:$ROOT/deploy/.env.prod"
scp -q -i "$KEY" deploy/app.env.prod "$USER_@$HOST:$ROOT/app/.env"
remote "chmod 600 $ROOT/deploy/.env.prod $ROOT/app/.env"
remote "mkdir -p $ROOT/app/storage/{app/public,framework/{cache/data,sessions,views},logs} $ROOT/app/bootstrap/cache"

LOG "Building PHP image"
remote "cd $ROOT && $COMPOSE build php"

LOG "Database"
remote "cd $ROOT && $COMPOSE up -d db"

LOG "Installing dependencies"
remote "cd $ROOT && $COMPOSE run --rm --no-deps -u root php sh -c 'git config --global --add safe.directory /var/www/html && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress'"

LOG "Migrating"
remote "cd $ROOT && $COMPOSE run --rm -u root php sh -c 'php artisan migrate --force && php artisan filament:assets && php artisan optimize && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'"

if [ "$first_deploy" = yes ]; then
    LOG "Seeding demo terminal (first deploy only)"
    remote "cd $ROOT && $COMPOSE run --rm -u www-data php php artisan db:seed --force && touch app/.seeded"
fi

LOG "Starting"
remote "cd $ROOT && $COMPOSE up -d && $COMPOSE restart worker scheduler"
remote "cd $ROOT && $COMPOSE ps --format '  {{.Service}}\t{{.Status}}'"
remote "docker run --rm --network loadline_default curlimages/curl:8.10.1 -s -o /dev/null -w '  bulkstream-web:8080 -> HTTP %{http_code}\n' --retry 15 --retry-connrefused --retry-delay 2 http://bulkstream-web:8080/admin/login"
