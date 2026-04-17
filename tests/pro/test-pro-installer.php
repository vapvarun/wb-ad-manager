<?php
/**
 * Pro installer — tables, options, DB version.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Pro_Installer extends Pro_Test_Case {

	public function test_db_version_is_set(): void {
		$this->assertSame( \WBAM_Pro\Core\Installer::DB_VERSION, get_option( 'wbam_pro_db_version' ) );
	}

	public function test_setup_complete_flag_exists(): void {
		$this->assertNotFalse( get_option( 'wbam_pro_setup_complete', false ) );
	}

	/**
	 * @dataProvider ledger_tables
	 */
	public function test_credits_sdk_table_exists( string $suffix ): void {
		global $wpdb;
		// SDK tables are prefixed with consumer prefix 'wbam' — see Credits_Bridge.
		$table = $wpdb->prefix . 'wbam_' . $suffix;
		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			"Missing SDK table {$table}"
		);
	}

	public function ledger_tables(): array {
		return array(
			array( 'ledger' ),
			array( 'ledger_hold' ),
		);
	}
}
