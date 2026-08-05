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

# Guard: every literal assets/... path referenced from shipped PHP must
# exist in the build payload. Catches .distignore patterns that silently
# strip runtime files (e.g. an unanchored "vendor" once removed
# assets/vendor/lucide.min.js and 404'd every frontend icon).
verify_runtime_assets() {
	local target="$1"
	local missing=0
	local ref
	while IFS= read -r ref; do
		if [ ! -f "$target/$ref" ]; then
			echo "ERROR: PHP references '$ref' but it is missing from the build payload." >&2
			missing=1
		fi
	done < <(grep -rhoE "assets/(css|js|vendor|images)/[A-Za-z0-9@_./-]+\.(css|js|png|svg|gif|woff2?)" \
		--include='*.php' "$target" | sort -u)
	if [ "$missing" -ne 0 ]; then
		echo "Build aborted: runtime assets stripped from the zip (check .distignore anchoring)." >&2
		exit 1
	fi
}

# Guard: the build payload may contain ONLY allowlisted top-level entries.
# Pattern-based excludes always lag reality (a new tool drops a new dotfile
# and it ships); an allowlist fails the build on ANYTHING unexpected.
# This is what .superpowers/ and AUDIT-VERDICT.md shipping in 3.1.0 taught.
verify_top_level() {
	local target="$1"; shift
	local allowed=" $* "
	local bad=0
	local entry name
	for entry in "$target"/* "$target"/.[!.]*; do
		[ -e "$entry" ] || continue
		name="$(basename "$entry")"
		if [[ "$allowed" != *" $name "* ]]; then
			echo "ERROR: unexpected top-level entry '$name' in build payload." >&2
			bad=1
		fi
	done
	if [ "$bad" -ne 0 ]; then
		echo "Build aborted: allowlist violation - a dev artifact would ship." >&2
		exit 1
	fi
}

FREE_ALLOWED="assets includes languages readme.txt uninstall.php wb-ads-rotator-with-split-test.php"

# ------------------------------------------------------------------
# 1. Free-only zip
# ------------------------------------------------------------------
FREE_TARGET="$BUILD_DIR/free/wb-ads-rotator-with-split-test"
mkdir -p "$FREE_TARGET"

# Read exclude list into an array. Using a while-read loop instead of
# `mapfile -t` so the script also works on bash 3.2 (macOS default).
FREE_EXCLUDES=()
while IFS= read -r line; do
	FREE_EXCLUDES+=("$line")
done < <(build_exclude_args "$FREE_DIR/.distignore")

rsync -a "${FREE_EXCLUDES[@]}" "$FREE_DIR/" "$FREE_TARGET/"

verify_runtime_assets "$FREE_TARGET"
# shellcheck disable=SC2086
verify_top_level "$FREE_TARGET" $FREE_ALLOWED

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
	# shellcheck disable=SC2086
	verify_top_level "$COMBO_FREE_TARGET" $FREE_ALLOWED

	# Pro exclude list. Use the free .distignore as a baseline — fine
	# because the pro plugin does not ship tests/ or phpstan/ itself yet.
	# libs/ policy (portfolio standard): runtime SDKs are bundled COMPLETE at
	# libs/<lib>/ and loaded directly (no composer at install time). The
	# leading '--include=/libs/***' wins over every later pattern exclude
	# ('*.md' etc.), so nothing inside a bundled SDK is ever stripped - a
	# partial SDK is a fatal error on a customer site. vendor/ is dev-only
	# toolchain and never ships.
	rsync -a \
		--include='/libs/***' \
		--exclude=/vendor \
		--exclude=.git --exclude=.github --exclude=node_modules --exclude=.superpowers \
		--exclude=tests --exclude=dist --exclude=docs --exclude=marketing \
		--exclude=/bin --exclude=/plan --exclude=/audit --exclude=/scripts \
		--exclude=.contract-audit-baseline.json --exclude=.phpcs-cache \
		--exclude=.distignore --exclude=.editorconfig --exclude=.gitattributes \
		--exclude=.gitignore --exclude=.phpunit.result.cache \
		--exclude=.eslintignore --exclude=.eslintrc.json --exclude=.stylelintrc.json \
		--exclude=.pa11yci --exclude=.phpcs.xml.dist --exclude=.DS_Store \
		--exclude=composer.json --exclude=composer.lock \
		--exclude=package.json --exclude=package-lock.json --exclude=Gruntfile.js \
		--exclude=phpunit.xml --exclude=phpunit.xml.dist \
		--exclude=phpcs.xml --exclude=phpcs.xml.dist \
		--exclude=phpstan.neon --exclude=phpstan-baseline.neon --exclude=phpstan-bootstrap.php \
		--exclude='*.md' --exclude=CLAUDE.md --exclude=sales-page.html \
		"$PRO_DIR/" "$COMBO_PRO_TARGET/"

	verify_top_level "$COMBO_PRO_TARGET" \
		assets demo-data demo-data-setup.php includes languages libs license \
		readme.txt templates wb-ad-manager-pro.php

	# The SDK bundle must ship COMPLETE - the plugin hard-requires its
	# bootstrap, and a stripped src/ or templates/ is a customer fatal.
	# Named-file assertions per the packaging standard, plus a 1:1 file-count
	# check against the source tree so no exclude pattern can nibble at it.
	SDK_SRC_DIR="$PRO_DIR/libs/wbcom-credits-sdk"
	SDK_OUT_DIR="$COMBO_PRO_TARGET/libs/wbcom-credits-sdk"
	if [ ! -f "$SDK_OUT_DIR/wbcom-credits-sdk.php" ] || [ ! -d "$SDK_OUT_DIR/src" ]; then
		echo "ERROR: Credits SDK bootstrap/src missing from Pro payload." >&2
		exit 1
	fi
	sdk_in=$(find "$SDK_SRC_DIR" -type f | wc -l | tr -d ' ')
	sdk_out=$(find "$SDK_OUT_DIR" -type f | wc -l | tr -d ' ')
	if [ "$sdk_in" != "$sdk_out" ]; then
		echo "ERROR: Credits SDK incomplete in payload ($sdk_out of $sdk_in files) - an exclude pattern is stripping it." >&2
		exit 1
	fi

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
