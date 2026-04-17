<?php
/**
 * Pro classifieds CPT + taxonomies.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Classified_CPT extends Pro_Test_Case {

	public function test_classified_cpt_registered(): void {
		$this->assertTrue( post_type_exists( 'wbam-classified' ) );
	}

	public function test_category_taxonomy_registered(): void {
		$this->assertTrue( taxonomy_exists( 'wbam-classified-cat' ) );
	}

	public function test_location_taxonomy_registered(): void {
		$this->assertTrue( taxonomy_exists( 'wbam-classified-loc' ) );
	}

	public function test_classified_supports_meta_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_classified_meta';
		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	}
}
