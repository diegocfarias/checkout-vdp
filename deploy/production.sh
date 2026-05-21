#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
PHP_BIN="${PHP_BIN:-php8.4}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
RUN_FRONTEND_BUILD="${RUN_FRONTEND_BUILD:-true}"
UPDATE_GIT="${UPDATE_GIT:-false}"

cd "$APP_DIR"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP binary '${PHP_BIN}' nao encontrado. Configure o site para PHP 8.4 ou defina PHP_BIN." >&2
    exit 1
fi

if ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
    echo "Composer binary '${COMPOSER_BIN}' nao encontrado." >&2
    exit 1
fi

if [ "$UPDATE_GIT" = "true" ]; then
    git fetch --prune origin "$DEPLOY_BRANCH"
    git checkout "$DEPLOY_BRANCH"
    git pull --ff-only origin "$DEPLOY_BRANCH"
fi

"$PHP_BIN" "$(command -v "$COMPOSER_BIN")" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

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
