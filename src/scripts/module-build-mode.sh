#!/bin/sh
# Prints how a module builds its frontend: `core` (its vite.config.ts calls defineModuleConfig,
# the core's toolchain), `legacy` (Legacy module toolchain: its own package.json brings Vite;
# removed once every module is on defineModuleConfig) or `none` (nothing to build).
# Usage: sh scripts/module-build-mode.sh <module-dir>
set -eu

DIR="${1:?Usage: module-build-mode.sh <module-dir>}"

if [ ! -f "$DIR/vite.config.ts" ]; then
    echo none
elif grep -q defineModuleConfig "$DIR/vite.config.ts"; then
    echo core
elif [ -f "$DIR/package.json" ]; then
    echo legacy
else
    echo none
fi
