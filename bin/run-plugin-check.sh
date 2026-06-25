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

# wp_cli wrapper. Using a function (not `WP="wp --path=$WP_ROOT"`) so paths
# containing spaces (Local-by-Flywheel: ".../Local Sites/...") don't tokenize
# wrong when expanded.
wp_cli() {
	wp --path="$WP_ROOT" "$@"
}

if ! wp_cli plugin is-installed plugin-check >/dev/null 2>&1; then
	echo "plugin-check not installed. Run:"
	echo "  wp --path=\"$WP_ROOT\" plugin install plugin-check --activate"
	exit 1
fi

# Uniform quality bar: full Plugin Check, no category exclusions.
# Same strictness applies to free (wp.org) and pro (self-hosted).
run_check() {
	local slug="$1"
	echo
	echo "== plugin-check: $slug =="
	# Ignored code (documented false positive, not a category exclusion):
	#   wp_function_not_compatible_with_requires_wp
	#     The Abilities API (wp_register_ability/_category, @since 6.9) is a
	#     progressive enhancement. Registration is gated on the 6.9-only
	#     wp_abilities_api_init / wp_abilities_api_categories_init hooks AND
	#     wrapped in function_exists(), so the plugin runs cleanly on its
	#     declared "Requires at least: 5.8" minimum. The sniff is a pure
	#     header-vs-@since comparison and cannot see the runtime gating;
	#     bumping the minimum to 6.9 would wrongly drop support for the vast
	#     majority of installs.
	wp_cli plugin check "$slug" \
		--severity=warning \
		--ignore-codes=wp_function_not_compatible_with_requires_wp \
		--format=table || EXIT=$?
}

EXIT=0
run_check "wb-ads-rotator-with-split-test"

if wp_cli plugin is-installed wb-ad-manager-pro >/dev/null 2>&1; then
	run_check "wb-ad-manager-pro"
fi

exit ${EXIT:-0}
