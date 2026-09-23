#!/usr/bin/env bash
#
# Local and CI quality gates for EXT:x402_paywall.
#
# Usage: Build/Scripts/runTests.sh -s <suite> [-d <sqlite|mariadb|mysql|postgres>]
#   suites: lint, cgl, cglFix, phpstan, unit, functional, ci (lint+cgl+phpstan+unit+functional)
#
# Database connection for functional tests (mariadb/mysql/postgres) is read from the environment:
#   typo3DatabaseHost, typo3DatabasePort, typo3DatabaseUsername, typo3DatabasePassword, typo3DatabaseName
set -euo pipefail

SUITE="ci"
DATABASE="sqlite"
PHP_BIN="${PHP_BINARY:-php}"

while getopts "s:d:p:h" OPTION; do
    case "${OPTION}" in
        s) SUITE="${OPTARG}" ;;
        d) DATABASE="${OPTARG}" ;;
        p) PHP_BIN="php${OPTARG}" ;;
        h) sed -n '2,12p' "$0"; exit 0 ;;
        *) exit 1 ;;
    esac
done

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"
BIN=".Build/bin"

run_lint() {
    find src tests Configuration -name '*.php' -not -path 'tests/Functional/Fixtures/*' -print0 | xargs -0 -n1 -P4 "${PHP_BIN}" -l > /dev/null
    echo "lint: ok"
}

run_cgl() {
    "${PHP_BIN}" "${BIN}/php-cs-fixer" fix --config=.php-cs-fixer.dist.php --dry-run --diff --using-cache=no "$@"
}

run_phpstan() {
    "${PHP_BIN}" "${BIN}/phpstan" analyse --configuration=phpstan.neon --no-progress --memory-limit=1G
}

run_unit() {
    "${PHP_BIN}" -d memory_limit=1G "${BIN}/phpunit" --configuration=Build/phpunit/UnitTests.xml
}

run_functional() {
    case "${DATABASE}" in
        sqlite)
            export typo3DatabaseDriver="pdo_sqlite"
            ;;
        mariadb|mysql)
            export typo3DatabaseDriver="${typo3DatabaseDriver:-mysqli}"
            export typo3DatabaseHost="${typo3DatabaseHost:-127.0.0.1}"
            export typo3DatabasePort="${typo3DatabasePort:-3306}"
            export typo3DatabaseUsername="${typo3DatabaseUsername:-root}"
            export typo3DatabasePassword="${typo3DatabasePassword:-root}"
            export typo3DatabaseName="${typo3DatabaseName:-func_test}"
            ;;
        postgres)
            export typo3DatabaseDriver="pdo_pgsql"
            export typo3DatabaseHost="${typo3DatabaseHost:-127.0.0.1}"
            export typo3DatabasePort="${typo3DatabasePort:-5432}"
            export typo3DatabaseUsername="${typo3DatabaseUsername:-postgres}"
            export typo3DatabasePassword="${typo3DatabasePassword:-postgres}"
            export typo3DatabaseName="${typo3DatabaseName:-func_test}"
            ;;
        *)
            echo "Unknown database: ${DATABASE}" >&2
            exit 1
            ;;
    esac
    # The TCA schema cache of a TYPO3 14 instance does not fit the default 128M.
    "${PHP_BIN}" -d memory_limit=1G "${BIN}/phpunit" --configuration=Build/phpunit/FunctionalTests.xml
}

case "${SUITE}" in
    lint) run_lint ;;
    cgl) run_cgl ;;
    cglFix) "${PHP_BIN}" "${BIN}/php-cs-fixer" fix --config=.php-cs-fixer.dist.php --using-cache=no ;;
    phpstan) run_phpstan ;;
    unit) run_unit ;;
    functional) run_functional ;;
    ci)
        run_lint
        run_cgl
        run_phpstan
        run_unit
        run_functional
        ;;
    *)
        echo "Unknown suite: ${SUITE}" >&2
        exit 1
        ;;
esac
