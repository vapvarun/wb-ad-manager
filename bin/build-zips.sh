#!/usr/bin/env bash
#
# Build two distribution zips:
#   1. wb-ads-rotator-with-split-test-<version>.zip   (free only)
#   2. wb-ad-manager-combo-<free>+<pro>.zip           (both plugin folders)
#
# Both respect the free plugin's .distignore. The pro folder reuses the
# same exclusion list so tests/phpstan/bin never leak into a release.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FREE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGINS_DIR="$(cd "$FREE_DIR/.." && pwd)"
PRO_DIR="$PLUGINS_DIR/wb-ad-manager-pro"
BUILD_DIR="$FREE_DIR/build"
DIST_DIR="$FREE_DIR/dist"

# Extract versions from plugin headers.
grep_version() {
	local file="$1"
	grep -i "^ \* Version:" "$file" | head -1 | sed 's/.*Version:[[:space:]]*//;s/[[:space:]]*$//'
}

FREE_VERSION="$(grep_version "$FREE_DIR/wb-ads-rotator-with-split-test.php")"
PRO_VERSION=""
if [ -f "$PRO_DIR/wb-ad-manager-pro.php" ]; then
	PRO_VERSION="$(grep_version "$PRO_DIR/wb-ad-manager-pro.php")"
fi

echo "Free version: $FREE_VERSION"
echo "Pro version:  ${PRO_VERSION:-<not found>}"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/free" "$BUILD_DIR/combo" "$DIST_DIR"

# rsync exclude list driven by .distignore.
build_exclude_args() {
	local distignore="$1"
	local args=()
	while IFS= read -r line; do
		# Skip blanks and comments.
		[ -z "$line" ] && continue
		[[ "$line" =~ ^# ]] && continue
		args+=(--exclude="$line")
	done < "$distignore"
	printf '%s\n' "${args[@]}"
}

# ------------------------------------------------------------------
# 1. Free-only zip
# ------------------------------------------------------------------
FREE_TARGET="$BUILD_DIR/free/wb-ads-rotator-with-split-test"
mkdir -p "$FREE_TARGET"

mapfile -t FREE_EXCLUDES < <(build_exclude_args "$FREE_DIR/.distignore")
rsync -a "${FREE_EXCLUDES[@]}" "$FREE_DIR/" "$FREE_TARGET/"

FREE_ZIP="$DIST_DIR/wb-ads-rotator-with-split-test-${FREE_VERSION}.zip"
rm -f "$FREE_ZIP"
( cd "$BUILD_DIR/free" && zip -rq "$FREE_ZIP" "wb-ads-rotator-with-split-test" )

# ------------------------------------------------------------------
# 2. Combo zip (only if pro is present)
# ------------------------------------------------------------------
if [ -n "$PRO_VERSION" ]; then
	COMBO_FREE_TARGET="$BUILD_DIR/combo/wb-ads-rotator-with-split-test"
	COMBO_PRO_TARGET="$BUILD_DIR/combo/wb-ad-manager-pro"
	mkdir -p "$COMBO_FREE_TARGET" "$COMBO_PRO_TARGET"

	rsync -a "${FREE_EXCLUDES[@]}" "$FREE_DIR/" "$COMBO_FREE_TARGET/"

	# Pro exclude list. Use the free .distignore as a baseline — fine
	# because the pro plugin does not ship tests/ or phpstan/ itself yet.
	rsync -a \
		--exclude=.git --exclude=.github --exclude=node_modules \
		--exclude=tests --exclude=dist --exclude=docs --exclude=marketing \
		--exclude=.distignore --exclude=.editorconfig --exclude=.gitattributes \
		--exclude=.gitignore --exclude=.phpunit.result.cache \
		--exclude=composer.json --exclude=composer.lock \
		--exclude=package.json --exclude=package-lock.json --exclude=Gruntfile.js \
		--exclude=phpunit.xml --exclude=phpunit.xml.dist \
		--exclude=phpcs.xml --exclude=phpcs.xml.dist \
		--exclude=phpstan.neon --exclude=phpstan-baseline.neon --exclude=phpstan-bootstrap.php \
		--exclude='*.md' --exclude=CLAUDE.md --exclude=sales-page.html \
		"$PRO_DIR/" "$COMBO_PRO_TARGET/"

	COMBO_ZIP="$DIST_DIR/wb-ad-manager-combo-${FREE_VERSION}+${PRO_VERSION}.zip"
	rm -f "$COMBO_ZIP"
	( cd "$BUILD_DIR/combo" && zip -rq "$COMBO_ZIP" "wb-ads-rotator-with-split-test" "wb-ad-manager-pro" )
fi

# ------------------------------------------------------------------
# Report
# ------------------------------------------------------------------
echo
echo "== Dist zips =="
for z in "$FREE_ZIP" "${COMBO_ZIP:-}"; do
	[ -z "$z" ] && continue
	[ ! -f "$z" ] && continue
	size=$(wc -c < "$z")
	sha=$(shasum -a 256 "$z" | awk '{print $1}')
	printf "  %s\n    size: %s bytes\n    sha256: %s\n" "$z" "$size" "$sha"
done
