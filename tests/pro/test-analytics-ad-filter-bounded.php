<?php
/**
 * Ad Analytics filter stays bounded as inventory grows.
 *
 * Regression guard for Basecamp card 10163619877: the filter dropdown
 * hydrated full WP_Post objects for EVERY ad on the site
 * (posts_per_page -1), rendering 1,220 options and growing the page
 * linearly with inventory. The list is now capped at FILTER_LIMIT
 * id/title pairs, the rest of the inventory is reachable via the
 * wbam_search_ads AJAX search, and a directly-linked ?ad_id= outside
 * the first page is still resolved into the dropdown.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Analytics\Analytics_Dashboard;

class Test_Analytics_Ad_Filter_Bounded extends Pro_Test_Case {

	private function ads_list( ?int $selected = null ): array {
		if ( null !== $selected ) {
			$_GET['ad_id'] = (string) $selected;
		} else {
			unset( $_GET['ad_id'] );
		}

		$dashboard = new Analytics_Dashboard();
		$method    = new \ReflectionMethod( Analytics_Dashboard::class, 'get_ads_list' );
		$method->setAccessible( true );

		$list = $method->invoke( $dashboard );
		unset( $_GET['ad_id'] );

		return $list;
	}

	public function test_filter_list_is_capped_regardless_of_inventory(): void {
		$count = Analytics_Dashboard::FILTER_LIMIT + 20;
		for ( $i = 0; $i < $count; $i++ ) {
			self::factory()->post->create(
				array(
					'post_type'  => 'wbam-ad',
					'post_title' => sprintf( 'Bounded Ad %03d', $i ),
				)
			);
		}

		$list = $this->ads_list();

		$this->assertLessThanOrEqual( Analytics_Dashboard::FILTER_LIMIT, count( $list ), 'The dropdown must never grow with inventory.' );
		$this->assertIsInt( $list[0]['id'], 'List entries are id/title pairs, not WP_Post objects.' );
	}

	public function test_direct_ad_id_outside_first_page_is_included(): void {
		for ( $i = 0; $i < Analytics_Dashboard::FILTER_LIMIT; $i++ ) {
			self::factory()->post->create(
				array(
					'post_type'  => 'wbam-ad',
					'post_title' => sprintf( 'AAA Early Ad %03d', $i ),
				)
			);
		}
		// Sorts far past the first page by title.
		$deep_link = (int) self::factory()->post->create(
			array(
				'post_type'  => 'wbam-ad',
				'post_title' => 'ZZZ Deep Linked Ad',
			)
		);

		$ids = wp_list_pluck( $this->ads_list( $deep_link ), 'id' );

		$this->assertContains( $deep_link, $ids, 'A bookmarked ?ad_id= must resolve even when it is not in the first page of options.' );
	}

	public function test_search_ajax_action_is_registered(): void {
		new Analytics_Dashboard();
		$this->assertNotFalse( has_action( 'wp_ajax_wbam_search_ads' ), 'The bounded dropdown depends on the AJAX search to reach the rest of the inventory.' );
	}
}
