<?php
/**
 * Placement -> accepted-formats map.
 *
 * Phase B of the format-aware placement matching plan. Rather than
 * editing 15+ placement classes and adding a method to each, we keep
 * one mapping file here and attach accepted_formats to the existing
 * wbam_get_placements registry via a filter at priority 20 (the ad
 * submission producer runs at default priority 10).
 *
 * Adding a new built-in placement? Add one row. Third-party placements
 * get an empty accepted_formats unless their authors populate via the
 * same filter — which means the matching layer treats them as
 * permissive (accept anything) until the author opts in.
 *
 * See docs/superpowers/plans/2026-04-15-format-aware-placement-matching.md
 *
 * @package WB_Ad_Manager
 * @since   2.8.1
 */

namespace WBAM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attach accepted_formats to each registered placement.
 */
class Placement_Format_Map {

	/**
	 * Wire the filter. Called from Plugin::init().
	 */
	public static function register() {
		// Priority 20: runs after Ad_Submission_Shortcodes::get_available_placements
		// (priority 10) which seeds the registry with name/description/group.
		add_filter( 'wbam_get_placements', array( __CLASS__, 'apply' ), 20 );
	}

	/**
	 * The canonical map. Placement slug => list of format slugs.
	 *
	 * Every entry should include Ad_Formats::RESPONSIVE so responsive
	 * ads can always render — a responsive creative by definition fits
	 * any slot.
	 *
	 * @return array<string, string[]>
	 */
	public static function map() {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$r = Ad_Formats::RESPONSIVE;

		$map = array(
			// Core placements (free).
			'header'              => array( 'leaderboard', 'large-leaderboard', 'banner', $r ),
			'footer'              => array( 'leaderboard', $r ),
			'before_content'      => array( 'leaderboard', 'medium-rectangle', 'large-rectangle', $r ),
			'after_content'       => array( 'leaderboard', 'medium-rectangle', 'large-rectangle', $r ),
			'paragraph'           => array( 'medium-rectangle', 'large-rectangle', $r ),
			'widget'              => array( 'medium-rectangle', 'skyscraper', 'wide-skyscraper', 'square', $r ),
			'before_archive'      => array( 'leaderboard', $r ),
			'after_archive'       => array( 'leaderboard', $r ),
			'sticky'              => array( 'mobile-banner', 'mobile-large-banner', $r ),
			'popup'               => array( 'medium-rectangle', 'large-rectangle', $r ),
			'comment'             => array( 'medium-rectangle', $r ),
			'comments'            => array( 'medium-rectangle', $r ), // alternate slug used by admin card.
			'shortcode'           => array( $r ), // inline, author controls the container.

			// BuddyPress.
			'bp_activity'         => array( 'medium-rectangle', $r ),
			'bp_before_members'   => array( 'leaderboard', 'medium-rectangle', $r ),
			'bp_after_members'    => array( 'leaderboard', 'medium-rectangle', $r ),
			'bp_before_groups'    => array( 'leaderboard', 'medium-rectangle', $r ),
			'bp_after_groups'     => array( 'leaderboard', 'medium-rectangle', $r ),

			// bbPress.
			'bbpress'             => array( 'leaderboard', 'medium-rectangle', $r ),

			// Jetonomy.
			'jetonomy_sidebar_before'      => array( 'medium-rectangle', 'wide-skyscraper', 'skyscraper', $r ),
			'jetonomy_sidebar_after_about' => array( 'medium-rectangle', 'wide-skyscraper', 'skyscraper', $r ),
			'jetonomy_sidebar_after'       => array( 'medium-rectangle', 'wide-skyscraper', 'skyscraper', $r ),
			'jetonomy_after_post_article'  => array( 'leaderboard', 'medium-rectangle', $r ),
			'jetonomy_before_replies'      => array( 'leaderboard', $r ),
			'jetonomy_between_replies'     => array( 'leaderboard', 'medium-rectangle', $r ),
			'jetonomy_after_replies'       => array( 'leaderboard', $r ),
		);

		/**
		 * Filter the placement -> accepted-formats map.
		 *
		 * Third-party placement authors should hook this filter to
		 * register their slug. Unregistered placements are treated as
		 * permissive (empty accepted_formats = accept anything).
		 *
		 * @since 2.8.1
		 * @param array<string, string[]> $map
		 */
		$map = apply_filters( 'wbam_placement_format_map', $map );

		return $map;
	}

	/**
	 * Apply the map to the wbam_get_placements registry.
	 *
	 * @param array $registry Placement registry (slug => metadata).
	 * @return array Registry with accepted_formats merged in.
	 */
	public static function apply( $registry ) {
		if ( ! is_array( $registry ) ) {
			return $registry;
		}

		$map = self::map();

		foreach ( $registry as $slug => $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}

			// Never overwrite a placement that already declared its own
			// formats (e.g. via Placement_Interface::get_accepted_formats
			// when we later add that method, or via a third-party hook
			// at an earlier priority).
			if ( ! empty( $meta['accepted_formats'] ) ) {
				continue;
			}

			$registry[ $slug ]['accepted_formats'] = isset( $map[ $slug ] ) ? $map[ $slug ] : array();
		}

		return $registry;
	}
}
