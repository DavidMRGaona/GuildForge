#!/bin/sh
# Run a module's PHPUnit suite inside the host application, whole or one part of it.
# Usage, from the host's src/ directory:
#   sh ../.github/scripts/run-module-tests.sh <module-name> [<part> <parts>]
# The module must be checked out at src/modules/<module-name> and the host's
# Composer dependencies installed (module phpunit.xml files boot ../../vendor/autoload.php).
# With <part> <parts>, it runs the files phpunit-part.php deals to that part, and part 1 first
# checks with check-phpunit-parts.sh that the parts together run every test exactly once.
# reusable-module-tests.yml looks for the usage line above to tell whether the host it checked
# out splits the suite: keep it as it is
set -eu

MODULE="${1:-}"
PART="${2:-}"
PARTS="${3:-}"

case "$MODULE" in
    '' | *[!a-z0-9-]*)
        echo "::error::Invalid module name: '$MODULE'"
        exit 1
        ;;
esac

if [ -n "$PART$PARTS" ]; then
    case "$PART:$PARTS" in
        *[!0-9:]* | :* | *: | 0* | *:0*)
            echo "::error::Invalid part '$PART' of '$PARTS': both must be numbers from 1"
            exit 1
            ;;
    esac
    if [ "$PART" -gt "$PARTS" ]; then
        echo "::error::Invalid part '$PART' of '$PARTS': the part cannot be greater than the number of parts"
        exit 1
    fi
fi

DIR="modules/$MODULE"

if [ ! -f "$DIR/phpunit.xml" ]; then
    echo "::notice::$MODULE has no phpunit.xml, skipping tests"
    exit 0
fi

if [ -z "$(find "$DIR/tests" -name '*Test.php' 2>/dev/null | head -n 1)" ]; then
    echo "::notice::$MODULE has no tests, skipping"
    exit 0
fi

# Git does not keep empty directories, so a fresh clone can lack some of the suite
# directories phpunit.xml declares (memberships only tracks tests/Unit); PHPUnit
# would warn about the missing ones
mkdir -p "$DIR/tests/Unit" "$DIR/tests/Integration" "$DIR/tests/Feature"

CONFIG="$DIR/phpunit.xml"

if [ -n "$PARTS" ]; then
    SCRIPTS="$(cd "$(dirname "$0")" && pwd)"

    # Once per run rather than in every part; listing runs no test, so it needs no database
    if [ "$PART" -eq 1 ]; then
        bash "$SCRIPTS/check-phpunit-parts.sh" "$PARTS" "$CONFIG"
    fi

    # A module with fewer test files than parts leaves the last parts with none: they write no
    # configuration and pass without running anything
    php "$SCRIPTS/phpunit-part.php" --configuration="$CONFIG" --allow-empty "$PART" "$PARTS" phpunit.part.xml
    if [ ! -f "$DIR/phpunit.part.xml" ]; then
        exit 0
    fi
    CONFIG="$DIR/phpunit.part.xml"
fi

# One process per CPU core. Each process gets its own database: on PostgreSQL (CI)
# Laravel creates <database>_test_<n>, a handful of connections well under the
# server's 100; on the phpunit.xml default, in-memory SQLite, each already has one.
# Going through artisan rather than calling paratest directly also gives each process
# its own compiled-view directory, without which two processes rendering the same mail
# view occasionally break each other
exec php artisan test --parallel --configuration="$CONFIG"
