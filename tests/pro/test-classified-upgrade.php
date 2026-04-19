<?php
/**
 * Classified upgrade-to-featured — regression gate for bug #9797839428
 * (paid listing packages cannot be selected).
 *
 * API-surface test: ensures the upgrade entry point exists. Full deduction
 * math is tested in test-credits-sdk.php.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Classified_Upgrade extends Pro_Test_Case {

	public function test_classified_class_exists(): void {
		$this->assertTrue(
			class_exists( '\\WBAM_Pro\\Modules\\Classifieds\\Classified' ),
			'Classified model must be loadable'
		);
	}

	public function test_upgrade_method_present(): void {
		$this->assertTrue(
			method_exists( '\\WBAM_Pro\\Modules\\Classifieds\\Classified', 'upgrade_to_featured' ),
			'Classified::upgrade_to_featured() must exist so the upgrade flow is wired'
		);
	}
}
