#!/usr/bin/env bash
# Checks that the parts phpunit-part.php writes run every test of a PHPUnit configuration exactly
# once: the test IDs PHPUnit lists for the whole suite must be the IDs of all the parts together,
# each one as many times. Listing loads the test classes and their data providers but runs nothing,
# so it needs neither a database nor an .env.
# Usage, from the host's src/ directory:
#   bash ../.github/scripts/check-phpunit-parts.sh <parts> [<phpunit.xml>]
# The configuration is the core's phpunit.xml by default, or a module's (modules/<name>/phpunit.xml).
# For a module, a part may have no tests when the module has fewer test files than parts.
set -euo pipefail

PARTS="${1:-}"
CONFIG="${2:-phpunit.xml}"
case "$PARTS" in
    '' | *[!0-9]* | 0*)
        echo "::error::Usage: check-phpunit-parts.sh <parts> [<phpunit.xml>], with parts a number from 1"
        exit 1
        ;;
esac

ALLOW_EMPTY=""
case "$CONFIG" in
    phpunit.xml) ;;
    modules/*/phpunit.xml) ALLOW_EMPTY="--allow-empty" ;;
    *)
        echo "::error::Invalid configuration '$CONFIG': it must be phpunit.xml or modules/<name>/phpunit.xml"
        exit 1
        ;;
esac

SCRIPTS="$(cd "$(dirname "$0")" && pwd)"
DIR="$(dirname "$CONFIG")"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK" "$DIR"/phpunit.part-check-*.xml' EXIT

# Every listed test is Class::method (with its data set, if any); the header lines are not. A part
# with no tests lists none, which grep reports as status 1
list_ids() {
    XDEBUG_MODE=off vendor/bin/phpunit --configuration="$1" --list-test-ids | { grep -F '::' || true; }
}

list_ids "$CONFIG" | LC_ALL=C sort > "$WORK/all"
: > "$WORK/parts"
SUMMARY=""
for PART in $(seq 1 "$PARTS"); do
    NAME="phpunit.part-check-$PART.xml"
    php "$SCRIPTS/phpunit-part.php" --configuration="$CONFIG" $ALLOW_EMPTY "$PART" "$PARTS" "$NAME"
    if [ -f "$DIR/$NAME" ]; then
        list_ids "$DIR/$NAME" > "$WORK/part-$PART"
    else
        # Only phpunit-part.php --allow-empty writes nothing, for a part with no test files
        : > "$WORK/part-$PART"
    fi
    COUNT="$(wc -l < "$WORK/part-$PART" | tr -d ' ')"
    if [ "$COUNT" -eq 0 ] && { [ -z "$ALLOW_EMPTY" ] || [ -f "$DIR/$NAME" ]; }; then
        echo "::error::Part $PART of $PARTS lists no tests"
        exit 1
    fi
    SUMMARY="$SUMMARY, part $PART: $COUNT"
    cat "$WORK/part-$PART" >> "$WORK/parts"
done
LC_ALL=C sort "$WORK/parts" -o "$WORK/parts"

TOTAL="$(wc -l < "$WORK/all" | tr -d ' ')"
if [ "$TOTAL" -eq 0 ]; then
    echo "::error::$CONFIG lists no tests"
    exit 1
fi
if ! cmp -s "$WORK/all" "$WORK/parts"; then
    echo "::error::The $PARTS parts do not run every test exactly once ($TOTAL tests in $CONFIG$SUMMARY)"
    echo "Lines starting with < run in no part, with > in more parts than they should:"
    diff "$WORK/all" "$WORK/parts" | grep -E '^[<>]' | head -n 40 || true
    exit 1
fi

echo "Every test runs exactly once: $TOTAL tests in $CONFIG$SUMMARY"
