#!/usr/bin/env bash
#
# Run the WordPress Plugin Check CLI against both plugins as a
# self-quality signal (not a wp.org submission gate).
#
# Requires: wp-cli + the `plugin-check` plugin installed on the Local site.
# Install once: wp plugin install plugin-check --activate

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"

WP="wp --path=$WP_ROOT"

if ! $WP plugin is-installed plugin-check >/dev/null 2>&1; then
	echo "plugin-check not installed. Run:"
	echo "  $WP plugin install plugin-check --activate"
	exit 1
fi

# Uniform quality bar: full Plugin Check, no category exclusions.
# Same strictness applies to free (wp.org) and pro (self-hosted).
run_check() {
	local slug="$1"
	echo
	echo "== plugin-check: $slug =="
	$WP plugin check "$slug" \
		--severity=warning \
		--format=table || EXIT=$?
}

EXIT=0
run_check "wb-ads-rotator-with-split-test"

if $WP plugin is-installed wb-ad-manager-pro >/dev/null 2>&1; then
	run_check "wb-ad-manager-pro"
fi

exit ${EXIT:-0}
