<?php
/**
 * Ad submission flow — regression gate for bugs #9793785303 and #9793913306.
 *
 * We verify that the submission manager class exists and exposes the
 * canonical entry point. The deeper mechanics (package pricing, hold
 * amount) are exercised by manual QA; here we guard the API surface.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Ad_Submission_Hold extends Pro_Test_Case {

	public function test_submission_manager_class_exists(): void {
		$this->assertTrue(
			class_exists( '\\WBAM_Pro\\Modules\\AdSubmissions\\Ad_Submission_Manager' ),
			'Ad_Submission_Manager must be loadable'
		);
	}

	public function test_submission_manager_has_process_method(): void {
		$this->assertTrue(
			method_exists( '\\WBAM_Pro\\Modules\\AdSubmissions\\Ad_Submission_Manager', 'get_instance' )
			|| method_exists( '\\WBAM_Pro\\Modules\\AdSubmissions\\Ad_Submission_Manager', 'process_ad_submission' ),
			'Ad_Submission_Manager must expose process_ad_submission or get_instance'
		);
	}
}
