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

if ( ! function_exists( 'wbam_icon' ) ) {
	/**
	 * Render a Lucide icon.
	 *
	 * Project standard is Lucide everywhere — no emojis, no dashicons. Templates
	 * in either plugin go through this helper so the markup and CSS hook class
	 * stay consistent. The `wbam-lucide` script handle is registered and
	 * enqueued automatically whenever this function is called.
	 *
	 * Shipped from the FREE plugin so pages that render without the pro plugin
	 * active still get consistent icons. The pro plugin's identical helper is
	 * guarded with function_exists() so free's definition wins at load order.
	 *
	 * @since 2.8.1
	 *
	 * @param string $name Lucide icon name (kebab-case, e.g. "pencil").
	 * @param array  $args {
	 *     Optional. Rendering options.
	 *
	 *     @type string $size  Size token: sm (16px), md (20px, default), lg (24px), xl (32px).
	 *     @type string $class Extra CSS classes appended to the <i>.
	 *     @type string $label Accessible label; empty string marks the icon decorative.
	 * }
	 * @return string HTML markup for the icon (pre-escaped).
	 */
	function wbam_icon( $name, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'size'  => 'md',
				'class' => '',
				'label' => '',
			)
		);

		$size_class = 'wbam-icon--' . sanitize_key( $args['size'] );
		$classes    = trim( 'wbam-icon ' . $size_class . ' ' . $args['class'] );

		$label_attr = ( '' !== $args['label'] )
			? ' role="img" aria-label="' . esc_attr( $args['label'] ) . '"'
			: ' aria-hidden="true"';

		// Lucide hydration only runs when the library is loaded. Auto-enqueue
		// on both frontend and admin so any template that calls the helper
		// automatically pulls the lib. Safe — wp_enqueue_script is idempotent.
		wp_enqueue_script( 'wbam-lucide' );
		wp_enqueue_style( 'wbam-lucide' );

		return sprintf(
			'<i data-lucide="%s" class="%s"%s></i>',
			esc_attr( sanitize_key( $name ) ),
			esc_attr( $classes ),
			$label_attr
		);
	}
}

if ( ! function_exists( 'wbam_register_lucide' ) ) {
	/**
	 * Register the wbam-lucide script + its .wbam-icon CSS rules.
	 *
	 * Single source of truth for lucide registration. Companion plugins must
	 * NOT re-register this handle — they only call
	 * `wp_enqueue_script( 'wbam-lucide' )` / `wp_enqueue_style( 'wbam-lucide' )`.
	 *
	 * Hooked at `init` priority 1 so the handle is in the WP script registry
	 * before any plugin's enqueue logic runs (typically `wp_enqueue_scripts`
	 * priority 10). The cost is a single row in the registry; the benefit is
	 * deterministic version control: when this plugin updates lucide, every
	 * companion plugin inherits the new version automatically.
	 *
	 * @since 2.8.1
	 * @since 2.9.0 Hooked on init@1 instead of wp_enqueue_scripts@5 so the
	 *              registry is populated before any plugin's enqueue logic runs.
	 */
	function wbam_register_lucide() {
		// Idempotency guard preserves correctness if some third-party code
		// registers `wbam-lucide` first; the contract says they shouldn't, but
		// we don't crash on misbehaviour.
		if ( ! wp_script_is( 'wbam-lucide', 'registered' ) ) {
			wp_register_script(
				'wbam-lucide',
				WBAM_URL . 'assets/vendor/lucide.min.js',
				array(),
				'0.460.0',
				true
			);
			wp_add_inline_script(
				'wbam-lucide',
				'document.addEventListener("DOMContentLoaded",function(){if(typeof lucide!=="undefined"){lucide.createIcons();}});'
			);
		}

		if ( ! wp_style_is( 'wbam-lucide', 'registered' ) ) {
			wp_register_style(
				'wbam-lucide',
				WBAM_URL . 'assets/css/lucide.css',
				array(),
				WBAM_VERSION
			);
		}
	}
	// Register early on init so the handle is available before any plugin or
	// theme reaches `wp_enqueue_scripts`/`admin_enqueue_scripts`. Priority 1
	// keeps it ahead of WP core's late-init handlers.
	add_action( 'init', 'wbam_register_lucide', 1 );
}

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
