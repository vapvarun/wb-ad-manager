<?php
/**
 * Ad_Formats::summarize_placement_compat() — the A1 audit fix.
 *
 * The ad-edit sizing section's live summary used to answer "which
 * placements accept this ad's format" while labelling itself "Will
 * render in:" — ignoring which placements the admin actually ticked in
 * the Placements metabox on the same screen. This pure, DOM-free
 * intersection logic is what fixes that: it must only report a ticked
 * placement as a match when its accepted formats actually include the
 * ad's format, and must report a ticked-but-mismatched placement
 * separately so the admin is warned rather than misled.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Ad_Formats;
use WP_UnitTestCase;

class Test_Ad_Format_Compat extends WP_UnitTestCase {

	/**
	 * A minimal registry fixture: three placements, each with a distinct
	 * accepted-formats list, mirroring the wbam_get_placements() shape.
	 *
	 * @return array<string,array>
	 */
	private function registry(): array {
		return array(
			'header'  => array(
				'name'             => 'Header',
				'accepted_formats' => array( 'leaderboard', 'banner' ),
			),
			'footer'  => array(
				'name'             => 'Footer',
				'accepted_formats' => array( 'leaderboard' ),
			),
			'sidebar' => array(
				'name'             => 'Lesson - Sidebar',
				'accepted_formats' => array( 'medium-rectangle', 'skyscraper' ),
			),
		);
	}

	public function test_no_placements_selected_returns_full_compatible_list_and_no_match_mismatch(): void {
		$out = Ad_Formats::summarize_placement_compat( $this->registry(), 'leaderboard', array() );

		$this->assertSame( array( 'header', 'footer' ), $out['compatible'] );
		$this->assertSame( array(), $out['match'] );
		$this->assertSame( array(), $out['mismatch'] );
	}

	public function test_ticked_placement_matching_format_is_reported_as_match(): void {
		$out = Ad_Formats::summarize_placement_compat( $this->registry(), 'leaderboard', array( 'header' ) );

		$this->assertSame( array( 'header' ), $out['match'] );
		$this->assertSame( array(), $out['mismatch'] );
	}

	/**
	 * The exact audit scenario: an ad's only ticked placement is
	 * "Lesson - Sidebar" (medium-rectangle/skyscraper only), but the ad's
	 * format is leaderboard. The old behaviour reported Header/Footer as
	 * "will render in" despite neither being ticked; the fix must report
	 * the ticked placement as a mismatch, not silently omit it or claim
	 * success elsewhere.
	 */
	public function test_ticked_placement_with_mismatched_format_is_reported_as_mismatch_only(): void {
		$out = Ad_Formats::summarize_placement_compat( $this->registry(), 'leaderboard', array( 'sidebar' ) );

		$this->assertSame( array(), $out['match'], 'A leaderboard ad must not match the sidebar slot.' );
		$this->assertSame( array( 'sidebar' ), $out['mismatch'] );
		$this->assertNotContains( 'header', $out['match'], 'Header must not appear just because it accepts this format — it was never ticked.' );
		$this->assertNotContains( 'footer', $out['match'], 'Footer must not appear just because it accepts this format — it was never ticked.' );
	}

	public function test_mixed_selection_splits_into_match_and_mismatch(): void {
		$out = Ad_Formats::summarize_placement_compat( $this->registry(), 'leaderboard', array( 'header', 'sidebar' ) );

		$this->assertSame( array( 'header' ), $out['match'] );
		$this->assertSame( array( 'sidebar' ), $out['mismatch'] );
	}

	public function test_responsive_format_matches_every_ticked_placement(): void {
		$out = Ad_Formats::summarize_placement_compat( $this->registry(), Ad_Formats::RESPONSIVE, array( 'header', 'footer', 'sidebar' ) );

		$this->assertSame( array( 'header', 'footer', 'sidebar' ), $out['match'] );
		$this->assertSame( array(), $out['mismatch'] );
	}

	public function test_selected_slug_not_in_registry_is_silently_ignored(): void {
		$out = Ad_Formats::summarize_placement_compat( $this->registry(), 'leaderboard', array( 'not_a_real_placement' ) );

		$this->assertSame( array(), $out['match'] );
		$this->assertSame( array(), $out['mismatch'] );
	}

	public function test_placement_with_empty_accepted_formats_does_not_match_non_responsive(): void {
		$registry = array(
			'unmapped' => array(
				'name'             => 'Unmapped Placement',
				'accepted_formats' => array(),
			),
		);

		$out = Ad_Formats::summarize_placement_compat( $registry, 'leaderboard', array( 'unmapped' ) );

		$this->assertSame( array(), $out['compatible'] );
		$this->assertSame( array( 'unmapped' ), $out['mismatch'] );
	}
}
