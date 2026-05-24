#!/usr/bin/env bash
set -euo pipefail

SUITE="ci"
PHP_BIN="${PHP_BINARY:-php}"

while getopts "s:p:" OPTION; do
    case "${OPTION}" in
        s) SUITE="${OPTARG}" ;;
        p) PHP_BIN="php${OPTARG}" ;;
        *) exit 1 ;;
    esac
done

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"

run_lint() {
    find Configuration src tests ext_localconf.php -name '*.php' -print0 \
        | xargs -0 -n1 "${PHP_BIN}" -l
}

case "${SUITE}" in
    lint)
        run_lint
        ;;
    phpstan)
        "${PHP_BIN}" vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=1G
        ;;
    unit)
        "${PHP_BIN}" vendor/bin/phpunit --configuration=phpunit.xml.dist
        ;;
    ci)
        composer validate --strict
        composer audit --no-interaction
        run_lint
        "${PHP_BIN}" vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=1G
        "${PHP_BIN}" vendor/bin/phpunit --configuration=phpunit.xml.dist
        ;;
    *)
        echo "Unknown suite: ${SUITE}" >&2
        exit 1
        ;;
esac
