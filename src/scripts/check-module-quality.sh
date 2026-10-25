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
    # The tests too, so their types do not drift while nobody looks
    STAN_PATHS=""
    for STAN_DIR in "$DIR/src" "$DIR/tests"; do
        if [ -d "$STAN_DIR" ]; then
            STAN_PATHS="$STAN_PATHS $STAN_DIR"
        fi
    done
    if [ -n "$STAN_PATHS" ]; then
        # Word splitting is wanted: the paths have no spaces (the module name is checked above)
        # shellcheck disable=SC2086
        run PHPStan vendor/bin/phpstan analyse --no-progress --memory-limit=2G --configuration=phpstan-modules.neon $STAN_PATHS
    fi
fi

if [ "$PART" = js ] || [ "$PART" = all ]; then
    if [ -d "$DIR/resources/js" ]; then
        run ESLint npx eslint "$DIR/resources/js"
        run Prettier npx prettier --check --no-error-on-unmatched-pattern "$DIR/resources/js/**/*.{ts,vue}"

        # Types against the core's own sources and the core's copies of the import map
        # packages (stubs/modules/module/tsconfig.json.stub), so a changed core prop or
        # composable fails here instead of in the browser
        if [ -n "$(find "$DIR/resources/js" \( -name '*.ts' -o -name '*.vue' \) 2>/dev/null | head -n 1)" ]; then
            if [ -f "$DIR/tsconfig.json" ]; then
                run vue-tsc sh -c "cd '$DIR' && ../../node_modules/.bin/vue-tsc --noEmit -p tsconfig.json"
            else
                echo "::error::$MODULE has TypeScript or Vue files but no tsconfig.json: copy stubs/modules/module/tsconfig.json.stub"
                FAILED="$FAILED vue-tsc"
            fi
        fi
    else
        echo "::notice::$MODULE has no resources/js, skipping ESLint and Prettier"
    fi

    MODE="$(sh scripts/module-build-mode.sh "$DIR")"
    if [ "$MODE" = invalid ]; then
        echo "::error::$DIR/vite.config.ts builds with neither the core's toolchain (defineModuleConfig, stubs/modules/module/vite.config.ts.stub) nor its own package.json"
        FAILED="$FAILED Build"
    elif [ "$MODE" = core ]; then
        # Only runtime libraries, never tooling or what the core shares or provides
        run Package node scripts/check-module-package.mjs "$DIR"
        if [ -f "$DIR/package.json" ]; then
            run "npm ci" sh -c "cd '$DIR' && npm ci --no-audit --no-fund"
        fi
        # The release build, into a scratch directory: a private import of the core or an
        # undeclared package fails here (vite.module.config.ts), not when publishing
        BUILD_OUT="$(mktemp -d)"
        run Build sh scripts/build-module.sh "$MODULE" --outDir "$BUILD_OUT" --logLevel warn
        rm -rf "$BUILD_OUT"
        # The module's Vitest suite, with the core's Vitest (vitest.module.config.ts); a new
        # module has the configuration before its first test
        if [ -f "$DIR/vitest.config.ts" ] && [ -n "$(find "$DIR/tests/js" -name '*.spec.ts' 2>/dev/null | head -n 1)" ]; then
            run Vitest sh -c "cd '$DIR' && ../../node_modules/.bin/vitest run"
        fi
    elif [ -f "$DIR/vitest.config.ts" ] && [ -f "$DIR/package-lock.json" ]; then
        # Legacy module toolchain: the module's own Vitest and test utils. Removed once every
        # module is on defineModuleConfig
        run Vitest sh -c "cd '$DIR' && npm ci --no-audit --no-fund && npx vitest run"
    fi
fi

if [ -n "$FAILED" ]; then
    echo "::error::Quality checks failed for $MODULE:$FAILED"
    exit 1
fi

echo "Quality checks passed for $MODULE"
