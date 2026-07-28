<?php
/**
 * Rejection-notification hook contract - regression gate for bug #9793827140
 * (notification not sent on ad rejection due to hook name mismatch).
 *
 * BuddyPress is not required. What matters is that the action the notification
 * class listens to is the one the submission manager fires, and that firing it
 * with the documented payload reaches listeners without blowing up.
 *
 * Previously this fired the hook with `0` in place of the submission object.
 * Email_Notifications::send_ad_rejected() immediately calls
 * $submission->get_advertiser(), so the test guaranteed the fatal its own
 * comment said it was checking for. It has errored on every run since.
 * Now it passes the shape the hook documents.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\AdSubmissions\Ad_Submission;

class Test_BP_Notifications extends Pro_Test_Case {

	private const REJECTION_HOOK = 'wbam_pro_ad_submission_rejected';

	/**
	 * The listener and the firer must agree on the name. A rename on one side
	 * only is exactly the bug this file exists for.
	 */
	public function test_rejection_hook_is_the_one_the_notifier_listens_to(): void {
		$this->assertTrue(
			has_action( self::REJECTION_HOOK ),
			'Nothing is listening to ' . self::REJECTION_HOOK . ' - the notifier and the submission manager have drifted apart.'
		);
	}

	/**
	 * Fire it the way the submission manager does: an Ad_Submission plus a
	 * reason string. An empty submission is a safe payload here because
	 * get_advertiser() returns null when advertiser_id is unset, so the
	 * production listener takes its early return instead of sending mail.
	 */
	public function test_rejection_action_reaches_listeners_with_documented_payload(): void {
		$ran = false;

		add_action(
			self::REJECTION_HOOK,
			function ( $submission, $reason ) use ( &$ran ) {
				$ran = ( $submission instanceof Ad_Submission ) && 'test reason' === $reason;
			},
			10,
			2
		);

		do_action( self::REJECTION_HOOK, new Ad_Submission(), 'test reason' );

		$this->assertTrue(
			$ran,
			'The rejection hook did not reach listeners with a submission object and a reason.'
		);
	}
}
