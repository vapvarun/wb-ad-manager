<?php
/**
 * Membership-plan saves follow POST-Redirect-GET.
 *
 * Regression guard for Basecamp card 10167158092: the save handler
 * lived inside render_membership_plans_page() - running mid-output and
 * echoing a success notice on the line BEFORE wp_safe_redirect(), so
 * the redirect was unreachable by construction. The owner got a
 * headers-already-sent warning, an empty form with no success signal,
 * and a duplicate plan on the natural second click. Handling now lives
 * on admin_init like this class's other POST handlers.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;

class Test_Membership_Plan_Prg extends Pro_Test_Case {

	public function test_handler_is_registered_on_admin_init(): void {
		$this->assertTrue(
			method_exists( Pro_Admin::class, 'handle_membership_plan_actions' ),
			'The pre-output handler must exist.'
		);

		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );
		$this->assertStringContainsString(
			"add_action( 'admin_init', array( \$this, 'handle_membership_plan_actions' ) );",
			$source,
			'Plan saves must be handled before any output, like the class\'s other POST handlers.'
		);
	}

	public function test_renderer_no_longer_handles_the_post(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );

		$render_start = strpos( $source, 'public function render_membership_plans_page()' );
		$this->assertNotFalse( $render_start );
		$render_body = substr( $source, $render_start, 2500 );

		$this->assertStringNotContainsString( "\$_POST['wbam_save_plan']", $render_body, 'POST handling inside the renderer is what made the redirect unreachable.' );
		$this->assertStringNotContainsString( 'wp_safe_redirect', $render_body, 'A renderer that redirects after echoing can only ever emit headers-already-sent warnings.' );
	}
}
