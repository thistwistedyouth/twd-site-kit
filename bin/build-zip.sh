#!/usr/bin/env bash
# Build dist/twd-site-kit-latest.zip and verify it against the source.
# Same pattern as the articles plugin: cp -r (not rsync), one top-level
# folder, no docs or tests inside, then a diff check before anything is pushed.
# Run from the repo root:  bash bin/build-zip.sh
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$(mktemp -d)"
VERIFY="$(mktemp -d)"
trap 'rm -rf "$BUILD" "$VERIFY"' EXIT

# 1. Copy ONLY the plugin files into a folder named twd-site-kit.
mkdir -p "$BUILD/twd-site-kit/includes"
cp "$ROOT/twd-site-kit.php" "$BUILD/twd-site-kit/"
cp -r "$ROOT/includes/." "$BUILD/twd-site-kit/includes/"
find "$BUILD/twd-site-kit" -name '.git*' -exec rm -rf {} + 2>/dev/null || true

# 2. Build the zip fresh.
mkdir -p "$ROOT/dist"
rm -f "$ROOT/dist/twd-site-kit-latest.zip"
( cd "$BUILD" && zip -r -X -q "$ROOT/dist/twd-site-kit-latest.zip" twd-site-kit )

# 3. Verify the zip against the source.
( cd "$VERIFY" && unzip -q "$ROOT/dist/twd-site-kit-latest.zip" )

TOP="$(unzip -Z1 "$ROOT/dist/twd-site-kit-latest.zip" | cut -d/ -f1 | sort -u)"
[ "$TOP" = "twd-site-kit" ] || { echo "FAIL: zip must have exactly one top-level folder twd-site-kit, got: $TOP"; exit 1; }

diff -q "$VERIFY/twd-site-kit/twd-site-kit.php" "$ROOT/twd-site-kit.php"
for f in "$ROOT"/includes/*.php; do
	diff -q "$VERIFY/twd-site-kit/includes/$(basename "$f")" "$f"
done

# Same set of files both ways: nothing missing, nothing extra.
( cd "$VERIFY/twd-site-kit" && find . -type f | sort ) > "$VERIFY/zip.list"
( cd "$ROOT" && { echo ./twd-site-kit.php; find ./includes -type f; } | sort ) > "$VERIFY/src.list"
diff "$VERIFY/zip.list" "$VERIFY/src.list"

# Nothing that must not ship.
if unzip -Z1 "$ROOT/dist/twd-site-kit-latest.zip" | grep -i -E 'claude|history|tests/|\.git|readme|bin/|dist/'; then
	echo "FAIL: the zip contains files that must not ship"; exit 1
fi

echo "OK: dist/twd-site-kit-latest.zip matches the source."
unzip -l "$ROOT/dist/twd-site-kit-latest.zip"
