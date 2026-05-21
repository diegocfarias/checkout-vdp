#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/home/forge/voedeprimeira.com}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
RUN_FRONTEND_BUILD="${RUN_FRONTEND_BUILD:-true}"

cd "$APP_DIR"

git fetch --prune origin "$DEPLOY_BRANCH"
git checkout "$DEPLOY_BRANCH"
git pull --ff-only origin "$DEPLOY_BRANCH"

"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ "$RUN_FRONTEND_BUILD" = "true" ] && command -v npm >/dev/null 2>&1; then
    npm install --no-audit --no-fund
    npm run build
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart

echo "Deploy producao concluido com sucesso em ${DEPLOY_BRANCH}."
