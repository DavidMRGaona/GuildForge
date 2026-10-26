#!/bin/sh
# Prints how a module builds its frontend: `core` (its vite.config.ts calls defineModuleConfig,
# the core's toolchain), `none` (nothing to build) or `invalid` (any other vite.config.ts, such
# as a module's own Vite, which callers must fail on).
# Usage: sh scripts/module-build-mode.sh <module-dir>
set -eu

DIR="${1:?Usage: module-build-mode.sh <module-dir>}"

if [ ! -f "$DIR/vite.config.ts" ]; then
    echo none
elif grep -q defineModuleConfig "$DIR/vite.config.ts"; then
    echo core
else
    echo invalid
fi
