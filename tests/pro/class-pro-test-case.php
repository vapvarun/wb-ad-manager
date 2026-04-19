<?php
/**
 * Base test case for pro tests. Skips the whole class if the pro plugin
 * was not loaded for this run (WBAM_RUN_PRO_TESTS != 1).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WP_UnitTestCase;

abstract class Pro_Test_Case extends WP_UnitTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		if ( getenv( 'WBAM_RUN_PRO_TESTS' ) !== '1' || ! defined( 'WBAM_PRO_VERSION' ) ) {
			self::markTestSkippedStatic();
		}
	}

	private static function markTestSkippedStatic(): void {
		// PHPUnit 9 allows static skip via a dummy method on class setup.
		throw new \PHPUnit\Framework\SkippedTestSuiteError( 'Pro tests require WBAM_RUN_PRO_TESTS=1 and the pro plugin installed.' );
	}
}
