<?php
/**
 * Server-authoritative pricing registration for the direct-pay checkout.
 *
 * Regression guard for Basecamp card 10143094785: PRO bundled Credits SDK
 * 1.3.0+ (which requires a `pricing` key at Registry::register()) without
 * registering one, so every /checkout/{gateway} request failed with a 503
 * `pricing_not_configured` and no advertiser could buy credits by card.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use Wbcom\Credits\Gateways\Pricing;
use Wbcom\Credits\Gateways\PricingException;
use WBAM_Pro\Core\Credits_Bridge;

class Test_Credits_Pricing_Registration extends Pro_Test_Case {

	private const SLUG = 'wbam-pro';

	/**
	 * The bug itself: with no pricing registered, resolve() throws
	 * `pricing_not_configured`. Passing resolve() proves the registration
	 * exists and checkout sessions can be created.
	 */
	public function test_pricing_is_registered_so_checkout_can_resolve(): void {
		$resolved = Pricing::resolve( self::SLUG, array( 'credits' => 100 ) );

		$this->assertSame( 'callback', $resolved['mode'] );
		$this->assertSame( 100, $resolved['credits'] );
		$this->assertSame( 100 * Credits_Bridge::get_credit_price_cents(), $resolved['price_cents'] );
	}

	/**
	 * The amount the gateway charges must equal the total the wallet tab
	 * displays — both sides read Credits_Bridge::get_credit_price_cents(),
	 * including the wbam_pro_credit_price_cents filter.
	 */
	public function test_charged_price_follows_the_display_price_filter(): void {
		$override = static function () {
			return 25;
		};
		add_filter( 'wbam_pro_credit_price_cents', $override );

		try {
			$this->assertSame( 25, Credits_Bridge::get_credit_price_cents() );

			$resolved = Pricing::resolve( self::SLUG, array( 'credits' => 40 ) );
			$this->assertSame( 40 * 25, $resolved['price_cents'] );
		} finally {
			remove_filter( 'wbam_pro_credit_price_cents', $override );
		}
	}

	/**
	 * Client-posted price_cents is ignored — the server-side calculation wins.
	 */
	public function test_client_supplied_price_cents_is_ignored(): void {
		$resolved = Pricing::resolve(
			self::SLUG,
			array(
				'credits'     => 100,
				'price_cents' => 1,
			)
		);

		$this->assertSame( 100 * Credits_Bridge::get_credit_price_cents(), $resolved['price_cents'] );
	}

	/**
	 * Bounds mirror the wallet input (min="10"): quantities below the
	 * floor are rejected before any gateway call.
	 */
	public function test_credits_below_minimum_are_rejected(): void {
		$this->expectException( PricingException::class );
		Pricing::resolve( self::SLUG, array( 'credits' => 5 ) );
	}
}
