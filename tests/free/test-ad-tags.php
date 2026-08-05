<?php
/**
 * Ad tag taxonomy contract.
 *
 * Ads were the only object in this plugin with no grouping, so a site owner
 * could not find one sponsor's inventory, stop it when a contract lapsed, or
 * delegate it. These lock in the decisions behind the fix, because each of them
 * is the kind of thing a later change could quietly reverse.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Ad_Tags extends WP_UnitTestCase {

	private const TAX = 'wbam_ad_tag';

	public function test_taxonomy_is_registered_and_attached_to_ads(): void {
		$this->assertTrue( taxonomy_exists( self::TAX ) );
		$this->assertTrue(
			is_object_in_taxonomy( 'wbam-ad', self::TAX ),
			'The taxonomy must be attached to wbam-ad, or nothing can be tagged.'
		);
	}

	/**
	 * The whole point. A folder holds an ad in one place; an ad is routinely a
	 * sponsor's ad AND a seasonal ad AND a leaderboard. If this fails the
	 * feature has been reduced to folders wearing a different name.
	 */
	public function test_an_ad_can_hold_several_tags_at_once(): void {
		$ad = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );

		wp_set_object_terms( $ad, array( 'sponsor-a', 'season-2026', 'leaderboards' ), self::TAX );

		$slugs = wp_get_object_terms( $ad, self::TAX, array( 'fields' => 'slugs' ) );
		sort( $slugs );

		$this->assertSame( array( 'leaderboards', 'season-2026', 'sponsor-a' ), $slugs );
	}

	/**
	 * Flat on purpose. Nesting only earns its keep when someone wants everything
	 * under a parent as one action, and it costs a checklist tree on every site
	 * to serve that. Flat can be made hierarchical later with terms intact; the
	 * reverse is not true once trees exist.
	 */
	public function test_taxonomy_is_flat(): void {
		$this->assertFalse( (bool) get_taxonomy( self::TAX )->hierarchical );
	}

	/**
	 * Off /wp/v2 for the same reason the CPT is - the plugin serves ads through
	 * /wbam/v1, which filters by enabled state and placement, and the core route
	 * has none of that filtering.
	 */
	public function test_taxonomy_is_not_exposed_on_core_rest(): void {
		$this->assertFalse(
			(bool) get_taxonomy( self::TAX )->show_in_rest,
			'Ad tags must stay off /wp/v2, matching the CPT they describe.'
		);
	}

	/**
	 * An ad has no front-end URL, so a tag archive would route somewhere that
	 * cannot render anything.
	 */
	public function test_taxonomy_has_no_public_archive(): void {
		$tax = get_taxonomy( self::TAX );

		$this->assertFalse( (bool) $tax->public );
		$this->assertFalse( (bool) $tax->publicly_queryable );
	}

	/**
	 * Inventing the filing system is an owner decision; filing an ad you can
	 * already edit is not. Getting this wrong either lets anyone with edit
	 * rights invent taxonomy, or stops the owner tagging their own inventory.
	 */
	public function test_capabilities_split_managing_from_assigning(): void {
		$caps = get_taxonomy( self::TAX )->cap;

		$this->assertSame( 'manage_options', $caps->manage_terms );
		$this->assertSame( 'manage_options', $caps->edit_terms );
		$this->assertSame( 'manage_options', $caps->delete_terms );
		$this->assertSame( 'edit_posts', $caps->assign_terms );
	}

	/**
	 * Tagging must not change which ads serve. Adding a taxonomy is an admin
	 * convenience; a site that ignores it should see no difference at all, and a
	 * site that uses it should not have its delivery silently altered before the
	 * targeting work is designed.
	 */
	public function test_tagging_an_ad_does_not_change_delivery(): void {
		$ad = self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad, '_wbam_enabled', 1 );
		update_post_meta( $ad, '_wbam_placements', array( 'before_content' ) );

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$before = count( $engine->get_ads_for_placement( 'before_content' ) );

		wp_set_object_terms( $ad, array( 'some-tag' ), self::TAX );
		wp_cache_flush();

		$after = count( $engine->get_ads_for_placement( 'before_content' ) );

		$this->assertSame( $before, $after, 'Applying a tag changed ad delivery. Tags are organisation only until targeting is designed.' );
	}

	/**
	 * The registration is filterable so a site can relabel or widen it without
	 * forking. If the filter stops being applied, that extension point is gone
	 * and nobody finds out until someone's override silently does nothing.
	 */
	public function test_registration_args_are_filterable(): void {
		$seen = false;

		$spy = function ( $args ) use ( &$seen ) {
			$seen = true;
			return $args;
		};

		add_filter( 'wbam_ad_tag_taxonomy_args', $spy );

		// Re-run registration the way init does.
		\WBAM\Core\Plugin::get_instance()->register_post_type();

		remove_filter( 'wbam_ad_tag_taxonomy_args', $spy );

		$this->assertTrue( $seen, 'wbam_ad_tag_taxonomy_args was not applied during registration.' );
	}
}
