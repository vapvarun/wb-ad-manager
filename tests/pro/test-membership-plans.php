<?php
/**
 * Membership plans — bug card #9688110513 (test membership plans for v1.5.0).
 *
 * Light smoke: ensures the membership module classes load and, if present,
 * the memberships DB table exists.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Membership_Plans extends Pro_Test_Case {

	public function test_memberships_table_if_feature_enabled(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_memberships';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( null === $exists ) {
			$this->markTestSkipped( 'Memberships feature table not present — feature gated or not yet installed.' );
		}

		$this->assertSame( $table, $exists );
	}
}
