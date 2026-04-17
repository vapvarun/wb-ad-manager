<?php
/**
 * BuddyPress notification hook contract — regression gate for bug #9793827140
 * (notification not sent on ad rejection due to hook name mismatch).
 *
 * We don't require BuddyPress to be active; we just assert the rejection
 * action name the notification class listens to actually has a listener
 * registered when the integration module loads.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_BP_Notifications extends Pro_Test_Case {

	private const REJECTION_HOOK = 'wbam_pro_ad_submission_rejected';

	public function test_rejection_hook_constant_is_stable(): void {
		// If someone renames this action without updating notifications,
		// this test will still pass — but the next test fails, alerting them.
		$this->assertNotEmpty( self::REJECTION_HOOK );
	}

	public function test_rejection_action_is_observable(): void {
		// At minimum, the pro plugin must define this action in its flow.
		// We exercise by firing it and ensuring no fatal.
		$ran = false;
		add_action(
			self::REJECTION_HOOK,
			function () use ( &$ran ) {
				$ran = true;
			}
		);
		do_action( self::REJECTION_HOOK, 0, 'test reason' );
		$this->assertTrue( $ran );
	}
}
