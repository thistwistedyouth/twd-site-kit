#!/usr/bin/env bash
# Build dist/twd-site-kit-latest.zip and verify it against the source.
# Same pattern as the articles plugin: cp -r (not rsync), one top-level folder,
# no docs or tests inside, then a diff check before anything is pushed.
# Run from the repo root:  bash bin/build-zip.sh
# Then: php bin/update-json.php --changelog="..."  and  php bin/check-release.php
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$(mktemp -d)"
VERIFY="$(mktemp -d)"
trap 'rm -rf "$BUILD" "$VERIFY"' EXIT

# What ships: the main file, readme.txt and these folders. Nothing else.
FOLDERS="includes assets packs starters templates"

# 1. Copy ONLY the plugin files into a folder named twd-site-kit.
mkdir -p "$BUILD/twd-site-kit"
cp "$ROOT/twd-site-kit.php" "$ROOT/readme.txt" "$BUILD/twd-site-kit/"
for d in $FOLDERS; do
	cp -r "$ROOT/$d" "$BUILD/twd-site-kit/$d"
done
find "$BUILD/twd-site-kit" -name '.git*' -exec rm -rf {} + 2>/dev/null || true

# 2. Build the zip fresh.
mkdir -p "$ROOT/dist"
rm -f "$ROOT/dist/twd-site-kit-latest.zip"
( cd "$BUILD" && zip -r -X -q "$ROOT/dist/twd-site-kit-latest.zip" twd-site-kit )

# 3. Verify the zip against the source.
( cd "$VERIFY" && unzip -q "$ROOT/dist/twd-site-kit-latest.zip" )

TOP="$(unzip -Z1 "$ROOT/dist/twd-site-kit-latest.zip" | cut -d/ -f1 | sort -u)"
[ "$TOP" = "twd-site-kit" ] || { echo "FAIL: zip must have exactly one top-level folder twd-site-kit, got: $TOP"; exit 1; }

# Every file is identical to its source (diff -q prints nothing when they match).
diff -q "$VERIFY/twd-site-kit/twd-site-kit.php" "$ROOT/twd-site-kit.php"
diff -q "$VERIFY/twd-site-kit/readme.txt" "$ROOT/readme.txt"
for d in $FOLDERS; do
	diff -r -q "$VERIFY/twd-site-kit/$d" "$ROOT/$d"
done

# Same set of files both ways: nothing missing, nothing extra.
( cd "$VERIFY/twd-site-kit" && find . -type f | sort ) > "$VERIFY/zip.list"
( cd "$ROOT" && { echo ./twd-site-kit.php; echo ./readme.txt; for d in $FOLDERS; do find ./$d -type f; done; } | sort ) > "$VERIFY/src.list"
diff "$VERIFY/zip.list" "$VERIFY/src.list"

# Nothing that must not ship.
if unzip -Z1 "$ROOT/dist/twd-site-kit-latest.zip" | grep -i -E 'claude|history|readme\.md|tests/|\.git|bin/|dist/'; then
	echo "FAIL: the zip contains files that must not ship"; exit 1
fi

# Everything the plugin loads is in the zip.
for f in twd-site-kit.php readme.txt includes/class-twd-sk-updater.php includes/class-twd-sk-registry.php includes/class-twd-sk-sanitizer.php includes/class-twd-sk-store.php includes/class-twd-sk-prompt.php includes/class-twd-sk-report.php includes/class-twd-sk-template.php includes/class-twd-sk-preview.php includes/class-twd-sk-rest.php includes/class-twd-sk-editor.php includes/class-twd-sk-safe.php includes/class-twd-sk-modules.php includes/class-twd-sk-page.php includes/class-twd-sk-packs.php includes/class-twd-sk-contrast.php includes/class-twd-sk-site.php includes/class-twd-sk-profile.php includes/class-twd-sk-chrome.php includes/class-twd-sk-elementor.php includes/class-twd-sk-starters.php includes/class-twd-sk-setup.php includes/class-twd-sk-mirror.php includes/class-twd-sk-images.php includes/class-twd-sk-quality.php includes/class-twd-sk-seo.php includes/class-twd-sk-assets.php includes/class-twd-sk-cli.php assets/twd-site-kit.css assets/twd-site-kit-editor.css assets/twd-site-kit-editor.js assets/twd-site-kit-editor-site.js assets/twd-site-kit-editor-seo.js templates/kit-page.php packs/sage.json packs/grove.json starters/_gallery.html; do
	[ -f "$VERIFY/twd-site-kit/$f" ] || { echo "FAIL: missing from the zip: $f"; exit 1; }
done

echo "OK: dist/twd-site-kit-latest.zip matches the source."
unzip -l "$ROOT/dist/twd-site-kit-latest.zip"

# 4. Release consistency: plugin header, TWD_SK_VERSION and readme.txt Stable tag agree,
#    the zip holds the same version in one twd-site-kit folder. The update JSON is only
#    a note here (it is updated after the build); bin/check-release.php checks it strictly.
echo
echo "sha256 of the built zip:"
( cd "$ROOT/dist" && sha256sum twd-site-kit-latest.zip )
php "$ROOT/bin/check-release.php" --allow-stale-json
