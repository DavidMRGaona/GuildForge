#!/bin/sh
# Run the core's code quality tools on a module, with the core's configuration.
# Usage, from the host's src/ directory: sh scripts/check-module-quality.sh <php|js|all> <module-name>
# The module must be at src/modules/<module-name>; php needs the host's Composer
# dependencies, js the host's npm ones. Every tool runs even when an earlier one
# fails, so a single run lists everything; the exit status is non-zero if any failed.
set -u

PART="${1:-}"
MODULE="${2:-}"

case "$PART" in
    php | js | all) ;;
    *)
        echo "::error::Usage: check-module-quality.sh <php|js|all> <module-name>"
        exit 1
        ;;
esac

case "$MODULE" in
    '' | *[!a-z0-9-]*)
        echo "::error::Invalid module name: '$MODULE'"
        exit 1
        ;;
esac

DIR="modules/$MODULE"

if [ ! -f "$DIR/module.json" ]; then
    echo "::error::$DIR/module.json not found"
    exit 1
fi

FAILED=""

# Runs a tool inside a log group and records its name when it fails
run() {
    NAME="$1"
    shift
    echo "::group::$NAME"
    if "$@"; then
        echo "::endgroup::"
    else
        echo "::endgroup::"
        echo "::error::$NAME failed for $MODULE"
        FAILED="$FAILED $NAME"
    fi
}

if [ "$PART" = php ] || [ "$PART" = all ]; then
    # Same order as the core: Rector's output is not Pint-clean
    if [ -d "$DIR/src" ]; then
        run Rector vendor/bin/rector process --dry-run --no-progress-bar "$DIR/src"
    fi
    run Pint vendor/bin/pint --test "$DIR"
    if [ -d "$DIR/src" ]; then
        run PHPStan vendor/bin/phpstan analyse --no-progress --memory-limit=2G --configuration=phpstan-modules.neon "$DIR/src"
    fi
fi

if [ "$PART" = js ] || [ "$PART" = all ]; then
    if [ -d "$DIR/resources/js" ]; then
        run ESLint npx eslint "$DIR/resources/js"
        run Prettier npx prettier --check --no-error-on-unmatched-pattern "$DIR/resources/js/**/*.{ts,vue}"
    else
        echo "::notice::$MODULE has no resources/js, skipping ESLint and Prettier"
    fi

    # The module's own Vitest suite, with its own dependencies (vitest, test utils)
    if [ -f "$DIR/vitest.config.ts" ] && [ -f "$DIR/package-lock.json" ]; then
        run Vitest sh -c "cd '$DIR' && npm ci --no-audit --no-fund && npx vitest run"
    fi
fi

if [ -n "$FAILED" ]; then
    echo "::error::Quality checks failed for $MODULE:$FAILED"
    exit 1
fi

echo "Quality checks passed for $MODULE"
