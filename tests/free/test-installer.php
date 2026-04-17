<?php
/**
 * Installer: tables, options, DB version.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Installer extends WP_UnitTestCase {

	private const EXPECTED_TABLES = array(
		'wbam_links',
		'wbam_link_categories',
		'wbam_link_clicks',
		'wbam_analytics',
		'wbam_email_submissions',
		'wbam_link_partnerships',
		'wbam_rate_limits',
	);

	public function test_all_tables_created_on_activation(): void {
		global $wpdb;

		foreach ( self::EXPECTED_TABLES as $base ) {
			$full  = $wpdb->prefix . $base;
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
			$this->assertSame( $full, $found, "Missing table {$full}" );
		}
	}

	public function test_db_version_option_matches_installer_constant(): void {
		$this->assertSame(
			\WBAM\Core\Installer::DB_VERSION,
			get_option( \WBAM\Core\Installer::DB_VERSION_OPTION )
		);
	}

	public function test_default_settings_option_seeded(): void {
		$this->assertNotFalse( get_option( 'wbam_settings', false ) );
	}

	public function test_cpt_registered_after_init(): void {
		$this->assertTrue( post_type_exists( 'wbam-ad' ), 'wbam-ad CPT must be registered' );
	}
}
