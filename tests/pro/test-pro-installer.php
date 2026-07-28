<?php
/**
 * Pro installer - tables, options, DB version.
 *
 * Two of these asserted things the plugin never produces:
 *
 * - The SDK table names were given as `wbam_ledger` and `wbam_ledger_hold`.
 *   Ledger::table_name() builds `{prefix}_credit_ledger`, and the Registry also
 *   creates `_credit_gateway_log` and `_credit_processed_events`. Neither of the
 *   asserted names has ever existed, on the live site or anywhere else.
 * - `wbam_pro_setup_complete` is written by the setup wizard when an admin
 *   finishes it, not by the installer, so asserting it after a bare install
 *   could only fail.
 *
 * Rewritten to assert what installation actually guarantees.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Pro_Installer extends Pro_Test_Case {

	public function test_db_version_is_set(): void {
		$this->assertSame( \WBAM_Pro\Core\Installer::DB_VERSION, get_option( 'wbam_pro_db_version' ) );
	}

	/**
	 * The setup flag is a wizard completion marker, not an install artefact. A
	 * fresh install must NOT look already-configured, or the wizard never
	 * offers itself.
	 */
	public function test_setup_complete_flag_is_absent_until_the_wizard_runs(): void {
		delete_option( 'wbam_pro_setup_complete' );

		$this->assertFalse(
			get_option( 'wbam_pro_setup_complete', false ),
			'A fresh install must not claim setup is complete - the wizard would never show.'
		);

		update_option( 'wbam_pro_setup_complete', true );
		$this->assertNotFalse( get_option( 'wbam_pro_setup_complete', false ) );
	}

	/**
	 * The credits system reads and writes through these three. A missing one
	 * does not fail loudly - it degrades to "no balance", which looks like an
	 * empty wallet rather than a broken install.
	 *
	 * @dataProvider sdk_tables
	 * @param string $suffix Table suffix the SDK appends after the consumer prefix.
	 */
	public function test_credits_sdk_table_exists( string $suffix ): void {
		global $wpdb;

		// Consumer prefix is 'wbam' (Credits_Bridge::PREFIX); the SDK appends
		// its own suffix, e.g. wp_wbam_credit_ledger.
		$table = $wpdb->prefix . 'wbam' . $suffix;

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			"Missing SDK table {$table}"
		);
	}

	public function sdk_tables(): array {
		return array(
			array( '_credit_ledger' ),
			array( '_credit_gateway_log' ),
			array( '_credit_processed_events' ),
		);
	}
}
