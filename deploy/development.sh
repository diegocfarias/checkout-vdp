#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-develop}"
PHP_BIN="${PHP_BIN:-}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
RUN_FRONTEND_BUILD="${RUN_FRONTEND_BUILD:-true}"
UPDATE_GIT="${UPDATE_GIT:-false}"

cd "$APP_DIR"

resolve_php_bin() {
    if [ -n "$PHP_BIN" ]; then
        if command -v "$PHP_BIN" >/dev/null 2>&1; then
            command -v "$PHP_BIN"
            return
        fi

        if [ -x "$PHP_BIN" ]; then
            echo "$PHP_BIN"
            return
        fi

        echo "PHP binary '${PHP_BIN}' nao encontrado. Configure PHP 8.4 no Forge ou defina PHP_BIN com o caminho completo." >&2
        exit 1
    fi

    for candidate in php8.4 /usr/bin/php8.4 /usr/local/bin/php8.4 /opt/plesk/php/8.4/bin/php; do
        if command -v "$candidate" >/dev/null 2>&1; then
            command -v "$candidate"
            return
        fi

        if [ -x "$candidate" ]; then
            echo "$candidate"
            return
        fi
    done

    if command -v php >/dev/null 2>&1 && php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") && version_compare(PHP_VERSION, "8.5.0", "<") ? 0 : 1);'; then
        command -v php
        return
    fi

    echo "PHP 8.4 nao encontrado. Instale/habilite PHP 8.4 no Forge ou defina PHP_BIN=/caminho/para/php8.4." >&2
    exit 1
}

PHP_BIN="$(resolve_php_bin)"
echo "Usando PHP: $("${PHP_BIN}" -r 'echo PHP_VERSION;') (${PHP_BIN})"

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

echo "Deploy dev concluido com sucesso em ${DEPLOY_BRANCH}."
