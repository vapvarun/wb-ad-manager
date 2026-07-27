<?php
/**
 * Placements settings table.
 *
 * Renders the two-gate placement matrix on the Settings screen and counts
 * how many live creatives each slot carries, so an admin closing a slot
 * can see what it costs before they save.
 *
 * @package WB_Ad_Manager
 * @since   2.11.0
 */

namespace WBAM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Placements settings table.
 */
class Placement_Settings {

	/**
	 * Enabled-ad count per placement ID.
	 *
	 * One grouped pass over the ad meta rather than a query per row — the
	 * settings screen lists every registered placement, and a per-row
	 * query would be an N+1 that grows with every integration module.
	 *
	 * `_wbam_placements` is a serialized array, so it cannot be GROUP BY'd
	 * in SQL. We fetch the enabled ads' meta in one IN() query and tally
	 * in PHP. Ad counts are in the hundreds at most, never the thousands.
	 *
	 * @since 2.11.0
	 * @return array<string,int> Placement ID => enabled ad count.
	 */
	public static function get_ad_counts() {
		global $wpdb;

		$cached = wp_cache_get( 'wbam_placement_ad_counts', 'wbam' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- aggregate for an
		// admin screen, cached below and invalidated on wbam_save_ad_meta.
		$rows = $wpdb->get_col(
			"SELECT pm.meta_value
			   FROM {$wpdb->postmeta} pm
			   JOIN {$wpdb->postmeta} en
			     ON en.post_id = pm.post_id
			    AND en.meta_key = '_wbam_enabled'
			    AND en.meta_value = '1'
			   JOIN {$wpdb->posts} p
			     ON p.ID = pm.post_id
			    AND p.post_type = 'wbam-ad'
			    AND p.post_status != 'trash'
			  WHERE pm.meta_key = '_wbam_placements'"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$counts = array();
		foreach ( (array) $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row ) as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug ) {
					continue;
				}
				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}

		wp_cache_set( 'wbam_placement_ad_counts', $counts, 'wbam', 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop the cached counts when an ad's placements change.
	 *
	 * @since 2.11.0
	 * @return void
	 */
	public static function clear_count_cache() {
		wp_cache_delete( 'wbam_placement_ad_counts', 'wbam' );
	}
}
