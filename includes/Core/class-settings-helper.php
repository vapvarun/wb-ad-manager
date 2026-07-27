<?php
/**
 * Settings Helper
 *
 * Centralized settings access for WB Ad Manager.
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings_Helper class.
 */
class Settings_Helper {

	/**
	 * Get settings value.
	 *
	 * @param string $key           Optional. Specific setting key to retrieve.
	 * @param mixed  $default_value Optional. Default value if key not found.
	 * @return mixed Settings array or specific value.
	 */
	public static function get( $key = null, $default_value = null ) {
		$settings = get_option( 'wbam_settings', array() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}

	/**
	 * Update a specific setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $key, $value ) {
		$settings         = self::get();
		$settings[ $key ] = $value;

		return update_option( 'wbam_settings', $settings );
	}

	/**
	 * Delete a specific setting.
	 *
	 * @param string $key Setting key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		$settings = self::get();

		if ( isset( $settings[ $key ] ) ) {
			unset( $settings[ $key ] );
			return update_option( 'wbam_settings', $settings );
		}

		return false;
	}

	/**
	 * Check if a setting exists and is truthy.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_enabled( $key ) {
		return (bool) self::get( $key, false );
	}

	/**
	 * Optional feature areas that a site owner can switch off.
	 *
	 * Each entry ships enabled. A site that does not use a feature can turn it
	 * off to remove its admin menu; a site that does use it is unaffected by
	 * the module system existing. Defaults are deliberately ON so that adding
	 * a module here never removes a menu from an existing install on update.
	 *
	 * @since 2.9.2
	 * @return array<string,bool> Module slug => default enabled state.
	 */
	public static function module_defaults() {
		/**
		 * Filter the optional module list and their default states.
		 *
		 * @since 2.9.2
		 * @param array<string,bool> $defaults Module slug => default enabled.
		 */
		return (array) apply_filters(
			'wbam_module_defaults',
			array(
				'links' => true,
			)
		);
	}

	/**
	 * Whether an optional module is switched on.
	 *
	 * Unknown slugs return true, so a module that has not been added to
	 * module_defaults() yet is never hidden by accident.
	 *
	 * @since 2.9.2
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public static function is_module_enabled( $slug ) {
		$defaults = self::module_defaults();
		$default  = array_key_exists( $slug, $defaults ) ? (bool) $defaults[ $slug ] : true;
		$modules  = (array) self::get( 'modules', array() );
		$enabled  = array_key_exists( $slug, $modules ) ? (bool) $modules[ $slug ] : $default;

		/**
		 * Filter whether a single module is enabled.
		 *
		 * PRO reads this so a module switched off in FREE also removes the
		 * submenus PRO contributes to it.
		 *
		 * @since 2.9.2
		 * @param bool   $enabled Whether the module is on.
		 * @param string $slug    Module slug.
		 */
		return (bool) apply_filters( 'wbam_is_module_enabled', $enabled, $slug );
	}

	/**
	 * Placement IDs usable on this site.
	 *
	 * An empty array means "all placements" — that is what keeps existing
	 * installs behaving identically after upgrade. It must never be
	 * reinterpreted as "none".
	 *
	 * @since 2.11.0
	 * @return string[] Placement IDs, or empty for all.
	 */
	public static function enabled_placements() {
		$settings = get_option( 'wbam_settings', array() );
		$ids      = isset( $settings['enabled_placements'] ) && is_array( $settings['enabled_placements'] )
			? $settings['enabled_placements']
			: array();

		$ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', $ids ) ) ) );

		/**
		 * Filter the placements usable on this site.
		 *
		 * @since 2.11.0
		 * @param string[] $ids Placement IDs. Empty array means all.
		 */
		return (array) apply_filters( 'wbam_enabled_placements', $ids );
	}

	/**
	 * Placement IDs that may be sold to advertisers.
	 *
	 * Always a subset of enabled_placements(): a slot the site has closed
	 * can never be sellable, whatever the stored value says. An empty
	 * stored value falls back to the site gate rather than to "none", so
	 * an admin who never opens this screen keeps today's behaviour.
	 *
	 * @since 2.11.0
	 * @return string[] Placement IDs, or empty for all.
	 */
	public static function advertiser_placements() {
		$settings = get_option( 'wbam_settings', array() );
		$ids      = isset( $settings['advertiser_placements'] ) && is_array( $settings['advertiser_placements'] )
			? $settings['advertiser_placements']
			: array();

		$ids  = array_values( array_unique( array_filter( array_map( 'sanitize_key', $ids ) ) ) );
		$site = self::enabled_placements();

		if ( empty( $ids ) ) {
			$ids = $site;
		} elseif ( ! empty( $site ) ) {
			$ids = array_values( array_intersect( $ids, $site ) );
		}

		/**
		 * Filter the placements sellable to advertisers.
		 *
		 * @since 2.11.0
		 * @param string[] $ids Placement IDs. Empty array means all.
		 */
		return (array) apply_filters( 'wbam_advertiser_placements', $ids );
	}
}
