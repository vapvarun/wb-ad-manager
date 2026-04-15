<?php
/**
 * Global helper functions for ad format matching.
 *
 * Thin procedural wrappers over WBAM\Core\Ad_Formats so third-party
 * code (themes, add-ons, site-specific mu-plugins) can call into the
 * matching layer without referencing the namespaced class directly.
 *
 * See docs/superpowers/plans/2026-04-15-format-aware-placement-matching.md
 *
 * @package WB_Ad_Manager
 * @since   2.8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WBAM\Core\Ad_Formats;

if ( ! function_exists( 'wbam_ad_fits_placement' ) ) {
	/**
	 * Whether the given ad is compatible with the given placement.
	 *
	 * Single source of truth for compatibility checks. The render
	 * engine, admin warn banner, ad submission flow, and package
	 * picker all route through this function.
	 *
	 * @since 2.8.1
	 * @param int    $ad_id          Ad post ID.
	 * @param string $placement_slug Placement slug.
	 * @return bool
	 */
	function wbam_ad_fits_placement( $ad_id, $placement_slug ) {
		return Ad_Formats::fits( (int) $ad_id, (string) $placement_slug );
	}
}

if ( ! function_exists( 'wbam_get_ad_format' ) ) {
	/**
	 * Resolve the format slug currently associated with an ad.
	 *
	 * @since 2.8.1
	 * @param int $ad_id Ad post ID.
	 * @return string Format slug (one of the taxonomy keys, or the
	 *                literal 'responsive' fallback).
	 */
	function wbam_get_ad_format( $ad_id ) {
		return Ad_Formats::get_ad_format( (int) $ad_id );
	}
}

if ( ! function_exists( 'wbam_detect_ad_format' ) ) {
	/**
	 * Infer a format slug from pixel dimensions.
	 *
	 * @since 2.8.1
	 * @param int $width  Pixel width.
	 * @param int $height Pixel height.
	 * @return string Format slug.
	 */
	function wbam_detect_ad_format( $width, $height ) {
		return Ad_Formats::detect_by_dimensions( (int) $width, (int) $height );
	}
}
