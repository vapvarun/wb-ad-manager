<?php
/**
 * Email capture persistence and rate-limit enforcement.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Email_Capture extends WP_UnitTestCase {

	public function test_email_submissions_table_exists(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_email_submissions';
		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	}

	public function test_rate_limits_table_exists(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_rate_limits';
		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	}

	public function test_rate_limits_has_expected_columns(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'wbam_rate_limits';
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		$this->assertContains( 'id', $columns );
	}
}
