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

	/**
	 * Render the two-gate placement matrix.
	 *
	 * Lists EVERY registered placement, not just selectable ones — an
	 * admin must be able to re-open a slot the site gate has closed, and
	 * a closed slot is absent from get_selectable_placements() by design.
	 *
	 * @since 2.11.0
	 * @return void
	 */
	public static function render_table() {
		$engine     = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$counts     = self::get_ad_counts();
		$site       = \WBAM\Core\Settings_Helper::enabled_placements();
		$advertiser = \WBAM\Core\Settings_Helper::advertiser_placements();
		$all_open   = empty( $site );
		$grouped    = $engine->get_placements_grouped();
		$option     = \WBAM\Admin\Settings::OPTION_NAME;
		?>
		<div class="wbam-placement-matrix__scroll">
		<table class="widefat wbam-placement-matrix">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Slot', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Site', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Advertisers', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Active ads', 'wb-ads-rotator-with-split-test' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $grouped as $group => $placements ) : ?>
				<tr class="wbam-placement-matrix__group">
					<th colspan="4" scope="colgroup"><?php echo esc_html( ucfirst( (string) $group ) ); ?></th>
				</tr>
				<?php
				foreach ( $placements as $id => $placement ) :
					if ( ! $placement->show_in_selector() ) {
						continue;
					}
					$count       = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
					$site_on     = $all_open || in_array( $id, $site, true );
					$adv_on      = empty( $advertiser ) || in_array( $id, $advertiser, true );
					$unavailable = ! $placement->is_available();
					?>
					<tr<?php echo $unavailable ? ' class="wbam-placement-matrix__row--unavailable"' : ''; ?>>
						<td>
							<strong><?php echo esc_html( $placement->get_name() ); ?></strong>
							<span class="description"><?php echo esc_html( $placement->get_description() ); ?></span>
							<?php if ( $unavailable ) : ?>
								<em><?php esc_html_e( 'Integration inactive', 'wb-ads-rotator-with-split-test' ); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<label>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: placement name. */
									echo esc_html( sprintf( __( 'Use %s on this site', 'wb-ads-rotator-with-split-test' ), $placement->get_name() ) );
									?>
								</span>
								<input type="checkbox"
									class="wbam-gate-site"
									data-placement="<?php echo esc_attr( $id ); ?>"
									data-count="<?php echo esc_attr( (string) $count ); ?>"
									name="<?php echo esc_attr( $option . '[enabled_placements][]' ); ?>"
									value="<?php echo esc_attr( $id ); ?>"
									<?php checked( $site_on ); ?> />
							</label>
						</td>
						<td>
							<label>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: placement name. */
									echo esc_html( sprintf( __( 'Sell %s to advertisers', 'wb-ads-rotator-with-split-test' ), $placement->get_name() ) );
									?>
								</span>
								<input type="checkbox"
									class="wbam-gate-advertiser"
									data-placement="<?php echo esc_attr( $id ); ?>"
									name="<?php echo esc_attr( $option . '[advertiser_placements][]' ); ?>"
									value="<?php echo esc_attr( $id ); ?>"
									<?php checked( $adv_on ); ?>
									<?php disabled( ! $site_on ); ?> />
							</label>
						</td>
						<td><?php echo esc_html( (string) $count ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}
}
