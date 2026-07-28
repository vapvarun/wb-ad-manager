#!/usr/bin/env bash
#
# WP-CLI data-flow assertions against the live Local site.
# Run from the free plugin root: bash bin/verify-data-flow.sh
#
# Exits 0 if everything is green, 1 on first failure.

set -uo pipefail

# Resolve WP site root (three levels up from this script: wp-content/plugins/plugin/bin).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"

# Quote the path so sites installed under directories with spaces
# (e.g. Local by Flywheel's "Local Sites") resolve correctly.
WP="wp --path=\"$WP_ROOT\""

PASS=0
FAIL=0

check() {
	local label="$1"
	local cmd="$2"
	local expected="$3"
	local actual

	actual="$(eval "$cmd" 2>&1 || true)"

	if [[ "$actual" == *"$expected"* ]]; then
		printf "  [OK]  %-45s\n" "$label"
		PASS=$((PASS + 1))
	else
		printf "  [FAIL] %-45s (got: %s)\n" "$label" "${actual:0:80}"
		FAIL=$((FAIL + 1))
	fi
}

echo "== WB Ad Manager — data flow verification =="
echo "Site: $WP_ROOT"
echo

# Table existence uses wp eval (PHP/wpdb) so it works on Local's socket.
table_check() {
	local label="$1"
	local suffix="$2"
	check "$label" \
		"$WP eval 'global \$wpdb; \$t = \$wpdb->prefix . \"$suffix\"; echo \$wpdb->get_var( \$wpdb->prepare( \"SHOW TABLES LIKE %s\", \$t ) ) === \$t ? \"ok\" : \"missing\";'" \
		"ok"
}

check "Free plugin is active"                 "$WP plugin is-active wb-ads-rotator-with-split-test && echo yes" "yes"
check "wbam-ad CPT registered"                "$WP eval 'echo post_type_exists(\"wbam-ad\") ? \"yes\" : \"no\";'" "yes"
check "wbam_db_version option set"            "$WP option get wbam_db_version"                                  "."
table_check "wbam_analytics table exists"       "wbam_analytics"
table_check "wbam_link_clicks table exists"     "wbam_link_clicks"
table_check "wbam_rate_limits table exists"     "wbam_rate_limits"
table_check "wbam_email_submissions table exists" "wbam_email_submissions"
table_check "wbam_links table exists"           "wbam_links"

# Pro checks — best-effort, skipped if pro plugin not active.
if eval "$WP plugin is-active wb-ad-manager-pro" >/dev/null 2>&1; then
	echo
	echo "-- Pro plugin detected --"
	# Classifieds is an optional module. Asserting its CPT unconditionally fails
	# on every site that has the module switched off - which is a supported
	# configuration, not a broken install. Ask the module flag first and assert
	# the CPT matches it, so the check is meaningful either way: registered when
	# on, absent when off.
	CLASSIFIEDS_ON=$(eval "$WP eval 'echo \WBAM_Pro\Core\Settings_Helper::is_module_enabled(\"classifieds\") ? \"yes\" : \"no\";'" 2>/dev/null | tr -d '[:space:]')
	if [ "$CLASSIFIEDS_ON" = "yes" ]; then
		check "wbam-classified CPT registered"   "$WP eval 'echo post_type_exists(\"wbam-classified\") ? \"yes\" : \"no\";'" "yes"
	else
		check "wbam-classified CPT absent (module off)" "$WP eval 'echo post_type_exists(\"wbam-classified\") ? \"yes\" : \"no\";'" "no"
	fi
	check "wbam_pro_db_version option set"       "$WP option get wbam_pro_db_version"                                       "."
	# Credits SDK prefixes its tables with the consumer prefix ('wbam')
	# then a type suffix, e.g. wbam_credit_ledger.
	table_check "credit ledger table exists"       "wbam_credit_ledger"
	table_check "wbam_classified_meta exists"      "wbam_classified_meta"
	table_check "campaigns table exists"           "wbam_campaigns"
	table_check "advertisers table exists"         "wbam_advertisers"
	table_check "ad_submissions table exists"      "wbam_ad_submissions"
	check "credits SDK class loadable"           "$WP eval 'echo class_exists(\"\\\\Wbcom\\\\Credits\\\\Credits\") ? \"yes\" : \"no\";'" "yes"
	check "daily aggregation cron scheduled"     "$WP cron event list --fields=hook --format=csv | grep -c wbam_pro_daily_aggregation" "1"
else
	echo
	echo "-- Pro plugin not active, skipping pro checks --"
fi

echo
echo "== Summary: $PASS passed, $FAIL failed =="
if [ "$FAIL" -gt 0 ]; then
	exit 1
fi
