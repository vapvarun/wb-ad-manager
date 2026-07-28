<?php
/**
 * Authorization on admin handlers that a nonce alone does not protect.
 *
 * The setup wizard is the case that matters. It renders from admin_init and
 * exits, which runs BEFORE wp-admin/admin.php resolves the capability attached
 * to add_dashboard_page() — so core's gate never fires for it. Its page check
 * matches on $_GET['page'] alone, on any admin file, and subscribers can load
 * profile.php. Before the fix, any logged-in user could render the wizard,
 * receive a valid wbam_setup_sample nonce and POST save_step to create ads.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Setup_Wizard;
use WP_UnitTestCase;

class Test_Authorization_Gates extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET['page'], $_POST['wbam_setup_nonce'], $_POST['sample_ads'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * The whole point: a subscriber must not be able to create ads through the
	 * wizard's save handler, even holding a nonce that is valid for them.
	 */
	public function test_subscriber_cannot_create_sample_ads(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$before = wp_count_posts( 'wbam-ad' );

		// A nonce the subscriber legitimately holds — nonces are per-user, so
		// this is exactly what they would get from a rendered wizard page.
		$_POST['wbam_setup_nonce'] = wp_create_nonce( 'wbam_setup_sample' );
		$_POST['sample_ads']       = array( 'sidebar_widget' );

		( new Setup_Wizard() )->step_sample_save();

		$after = wp_count_posts( 'wbam-ad' );

		$this->assertSame(
			$before->publish + $before->draft,
			$after->publish + $after->draft,
			'A subscriber holding a valid nonce created ads through the wizard.'
		);
	}

	/**
	 * The same handler must still work for the administrator it was built for,
	 * so the guard is proven to block the right people rather than everyone.
	 */
	public function test_administrator_can_still_create_sample_ads(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$before = wp_count_posts( 'wbam-ad' );

		$_POST['wbam_setup_nonce'] = wp_create_nonce( 'wbam_setup_sample' );
		$_POST['sample_ads']       = array( 'sidebar_widget' );

		// The handler ends in wp_safe_redirect() + exit, which cannot run under
		// PHPUnit. Escape through the plugin's own hook, which fires after the
		// ads are created and before the redirect.
		$escape = static function () {
			throw new \RuntimeException( 'redirect reached' );
		};
		add_action( 'wbam_setup_wizard_sample_save_after', $escape );

		try {
			( new Setup_Wizard() )->step_sample_save();
			$this->fail( 'Expected the handler to reach its post-creation hook.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect reached', $e->getMessage() );
		} finally {
			remove_action( 'wbam_setup_wizard_sample_save_after', $escape );
		}

		$after = wp_count_posts( 'wbam-ad' );

		$this->assertGreaterThan(
			$before->publish + $before->draft,
			$after->publish + $after->draft,
			'The guard blocked the administrator it is supposed to allow.'
		);
	}

	/**
	 * setup_wizard() itself must bail for an unprivileged user before it starts
	 * buffering and rendering. If it returns without output, the gate held.
	 */
	public function test_wizard_does_not_render_for_subscriber(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		// Matches on page alone — this is reachable from profile.php too.
		$_GET['page'] = 'wbam-setup';

		$level = ob_get_level();
		( new Setup_Wizard() )->setup_wizard();

		$this->assertSame(
			$level,
			ob_get_level(),
			'The wizard began buffering output for a subscriber instead of bailing.'
		);
	}
}
