<?php
/**
 * Ad Submissions "All" equals the real row count.
 *
 * Regression guard for Basecamp card 10166280287: get_status_counts()
 * already carries the total under 'all', and the list table summed the
 * whole array - adding the total to its own parts, so the All filter
 * read exactly 2x the real number for any status mix.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Submissions_Status_Counts extends Pro_Test_Case {

	public function test_all_key_equals_the_sum_of_the_status_parts(): void {
		global $wpdb;
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->get_or_create( $user );

		foreach ( array( 'pending', 'pending', 'rejected' ) as $i => $status ) {
			// The counts query excludes submissions whose ad post is gone,
			// so each fixture row needs a real wbam-ad post behind it.
			$ad_id = (int) self::factory()->post->create(
				array(
					'post_type'  => 'wbam-ad',
					'post_title' => 'Count fixture ' . $i,
				)
			);
			$wpdb->insert(
				$wpdb->prefix . 'wbam_ad_submissions',
				array(
					'advertiser_id' => (int) $advertiser->id,
					'ad_id'         => $ad_id,
					'status'        => $status,
					'submitted_at'  => current_time( 'mysql' ),
				)
			);
		}

		$counts = Ad_Submission_Manager::get_instance()->get_status_counts();

		$parts = $counts;
		unset( $parts['all'] );

		$this->assertSame( array_sum( $parts ), (int) $counts['all'], "The 'all' key is the total; summing it with its parts is what doubled the UI." );
		$this->assertSame( 3, (int) $counts['all'] );
	}
}
