#!/bin/sh
# Builds a module's frontend with the core's toolchain (vite.module.config.ts).
# Usage, from the host's src/ directory: sh scripts/build-module.sh <module-name> [vite build args…]
# The module must be at src/modules/<module-name>; MODULE_STANDALONE_OUTPUT=true writes the
# build into the module (modules/<module-name>/public/build) instead of public/build/modules.
set -eu

MODULE="${1:-}"

case "$MODULE" in
    '' | *[!a-z0-9-]*)
        echo "::error::Invalid module name: '$MODULE'"
        exit 1
        ;;
esac
shift

CONFIG="modules/$MODULE/vite.config.ts"

if [ ! -f "$CONFIG" ]; then
    echo "::error::$CONFIG not found"
    exit 1
fi

exec npx vite build --config "$CONFIG" "$@"
