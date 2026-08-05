<?php
/**
 * Direct-pay claim route + money-mode crediting through the bundled SDK.
 *
 * Regression guard for Basecamp card 10134503233: Stripe captured the
 * payment but credits never landed because crediting was webhook-only —
 * and when the SDK 1.6.0 claim path first ran, the gateway credited the
 * raw credit count into a minor-unit ledger (100 credits arrived as 1).
 * These tests pin the bundled SDK's claim surface and unit conversion so
 * a bundle refresh cannot silently revert either fix.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;

class Test_Credits_Gateway_Claim extends Pro_Test_Case {

	private const SLUG = 'wbam-pro';

	/**
	 * The claim route must exist — it is what turns "payment captured,
	 * credits never arrive" into "credits land on redirect-return" for
	 * every site without a configured webhook.
	 */
	public function test_claim_route_is_registered(): void {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey(
			'/wbcom-credits/v1/' . self::SLUG . '/claim/(?P<gateway>[a-z0-9_-]+)',
			$routes,
			'Bundled Credits SDK must expose the 1.6.0 synchronous claim endpoint.'
		);
	}

	/**
	 * wbam-pro is a money consumer (minor-unit ledger). A gateway credit
	 * of N credits must move the balance by N major units — not by N
	 * minor units, which is the 100x-shortfall bug the claim rollout
	 * uncovered in the SDK's gateway orchestrator.
	 */
	public function test_gateway_topup_credits_money_ledger_in_minor_units(): void {
		$user = self::factory()->user->create();

		$this->assertTrue( \Wbcom\Credits\Credits::is_money( self::SLUG ), 'wbam-pro must register SDK money mode.' );

		$before = \Wbcom\Credits\Credits::get_balance( self::SLUG, $user );
		\Wbcom\Credits\Credits::topup_money( self::SLUG, $user, 100, '', 'gateway:stripe:cs_test_unit' );
		$after = \Wbcom\Credits\Credits::get_balance( self::SLUG, $user );

		$factor = (int) wbam_get_currency_minor_factor();
		$this->assertSame( 100 * $factor, $after - $before, 'A 100-credit gateway topup must write 100 x minor-factor ledger units.' );
	}
}
