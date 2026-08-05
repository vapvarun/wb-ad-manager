<?php
/**
 * Sidebar category counts roll up descendants like the filter does.
 *
 * Regression guard for Basecamp card 10163839788: parent categories
 * showed (0) while the filter (include_children) returned their
 * children's listings — real inventory hidden behind a label saying
 * there is none. Counts now attribute each active listing to its term
 * and every ancestor, de-duplicated per listing, so the badge always
 * equals what clicking the filter returns.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Category_Count_Rollup extends Pro_Test_Case {

	private int $parent_cat;
	private int $child_cat;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		// create() derives status from require_approval; counts only see
		// active listings, so approval must be off for these fixtures.
		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		$parent           = wp_insert_term( 'Rollup Electronics', 'wbam-classified-cat' );
		$this->parent_cat = (int) $parent['term_id'];
		$child            = wp_insert_term( 'Rollup Cameras', 'wbam-classified-cat', array( 'parent' => $this->parent_cat ) );
		$this->child_cat  = (int) $child['term_id'];
	}

	private function make_active_listing( array $term_ids ): object {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Rollup listing ' . wp_generate_password( 6, false ),
				'description'   => 'Rollup test.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->assertNotWPError( $classified );
		wp_set_object_terms( (int) $classified->post_id, $term_ids, 'wbam-classified-cat' );

		return $classified;
	}

	private function count_for( int $term_id ): int {
		foreach ( Classified_Manager::get_instance()->get_categories_with_counts() as $term ) {
			if ( (int) $term->term_id === $term_id ) {
				return (int) $term->count;
			}
		}
		$this->fail( "Term {$term_id} missing from get_categories_with_counts()." );
	}

	public function test_parent_count_includes_child_listings(): void {
		$this->make_active_listing( array( $this->child_cat ) );

		$this->assertSame( 1, $this->count_for( $this->parent_cat ), 'A parent must advertise the inventory its subtree holds - the filter returns it.' );
		$this->assertSame( 1, $this->count_for( $this->child_cat ) );
	}

	public function test_listing_in_parent_and_child_counts_once(): void {
		$this->make_active_listing( array( $this->parent_cat, $this->child_cat ) );

		$this->assertSame( 1, $this->count_for( $this->parent_cat ), 'Distinct-listing semantics: assigned to parent AND child still counts once, matching the filter.' );
	}
}
