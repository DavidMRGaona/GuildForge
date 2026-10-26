#!/bin/sh
# Run a module's PHPUnit suite inside the host application.
# Usage, from the host's src/ directory: sh ../.github/scripts/run-module-tests.sh <module-name>
# The module must be checked out at src/modules/<module-name> and the host's
# Composer dependencies installed (module phpunit.xml files boot ../../vendor/autoload.php).
set -eu

MODULE="${1:-}"

case "$MODULE" in
    '' | *[!a-z0-9-]*)
        echo "::error::Invalid module name: '$MODULE'"
        exit 1
        ;;
esac

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

# One process per CPU core. Each process gets its own database: on PostgreSQL (CI)
# Laravel creates <database>_test_<n>, a handful of connections well under the
# server's 100; on the phpunit.xml default, in-memory SQLite, each already has one.
# Going through artisan rather than calling paratest directly also gives each process
# its own compiled-view directory, without which two processes rendering the same mail
# view occasionally break each other
exec php artisan test --parallel --configuration="$DIR/phpunit.xml"
