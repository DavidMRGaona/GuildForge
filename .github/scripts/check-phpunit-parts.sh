#!/usr/bin/env bash
# Checks that the parts phpunit-part.php writes run every test of phpunit.xml exactly once: the
# test IDs PHPUnit lists for the whole suite must be the IDs of all the parts together, each one
# as many times. Listing loads the test classes and their data providers but runs nothing, so it
# needs neither a database nor an .env.
# Usage, from the host's src/ directory: bash ../.github/scripts/check-phpunit-parts.sh <parts>
set -euo pipefail

PARTS="${1:-}"
case "$PARTS" in
    '' | *[!0-9]* | 0*)
        echo "::error::Usage: check-phpunit-parts.sh <parts>, a number from 1"
        exit 1
        ;;
esac

SCRIPTS="$(cd "$(dirname "$0")" && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK" phpunit.part-check-*.xml' EXIT

# Every listed test is Class::method (with its data set, if any); the header lines are not
list_ids() {
    XDEBUG_MODE=off vendor/bin/phpunit --configuration="$1" --list-test-ids | grep -F '::'
}

list_ids phpunit.xml | LC_ALL=C sort > "$WORK/all"
: > "$WORK/parts"
SUMMARY=""
for PART in $(seq 1 "$PARTS"); do
    CONFIG="phpunit.part-check-$PART.xml"
    php "$SCRIPTS/phpunit-part.php" "$PART" "$PARTS" "$CONFIG"
    list_ids "$CONFIG" > "$WORK/part-$PART"
    COUNT="$(wc -l < "$WORK/part-$PART" | tr -d ' ')"
    if [ "$COUNT" -eq 0 ]; then
        echo "::error::Part $PART of $PARTS lists no tests"
        exit 1
    fi
    SUMMARY="$SUMMARY, part $PART: $COUNT"
    cat "$WORK/part-$PART" >> "$WORK/parts"
done
LC_ALL=C sort "$WORK/parts" -o "$WORK/parts"

TOTAL="$(wc -l < "$WORK/all" | tr -d ' ')"
if ! cmp -s "$WORK/all" "$WORK/parts"; then
    echo "::error::The $PARTS parts do not run every test exactly once ($TOTAL tests in phpunit.xml$SUMMARY)"
    echo "Lines starting with < run in no part, with > in more parts than they should:"
    diff "$WORK/all" "$WORK/parts" | grep -E '^[<>]' | head -n 40 || true
    exit 1
fi

echo "Every test runs exactly once: $TOTAL tests in phpunit.xml$SUMMARY"
