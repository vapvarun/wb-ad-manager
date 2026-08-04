<?php
/**
 * Gateway status badge vs optional credentials.
 *
 * Regression guard for Basecamp card 10143130518: the badge guessed
 * requiredness from field types ("every password field is required"),
 * so Stripe showed "Incomplete" when only the OPTIONAL webhook signing
 * secret was empty — even though payments complete without it via the
 * SDK 1.6.0 redirect claim. The badge now honors the `required` flags
 * gateways declare on their settings fields.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use Wbcom\Credits\Gateways\PayPal;
use Wbcom\Credits\Gateways\Stripe;
use WBAM_Pro\Admin\Credits_Settings;

class Test_Credits_Gateway_Status_Badge extends Pro_Test_Case {

	/**
	 * @param array<string, mixed> $settings Stored gateway settings.
	 * @return array{state:string,label:string,hint:string}
	 */
	private function status_for( object $gateway, array $settings ): array {
		$method = new \ReflectionMethod( Credits_Settings::class, 'compute_gateway_status' );
		$method->setAccessible( true );

		return $method->invoke( new Credits_Settings(), $gateway, $settings );
	}

	public function test_stripe_with_keys_but_no_webhook_secret_is_not_incomplete(): void {
		$status = $this->status_for(
			new Stripe(),
			array(
				'enabled'         => '1',
				'mode'            => 'test',
				'publishable_key' => 'pk_test_x',
				'secret_key'      => 'sk_test_x',
				'webhook_secret'  => '',
			)
		);

		$this->assertSame( 'test', $status['state'], 'Optional webhook secret must not flip the badge to incomplete.' );
	}

	public function test_stripe_without_secret_key_is_incomplete(): void {
		$status = $this->status_for(
			new Stripe(),
			array(
				'enabled'         => '1',
				'mode'            => 'test',
				'publishable_key' => 'pk_test_x',
				'secret_key'      => '',
			)
		);

		$this->assertSame( 'incomplete', $status['state'] );
	}

	public function test_paypal_without_webhook_id_is_incomplete(): void {
		$status = $this->status_for(
			new PayPal(),
			array(
				'enabled'       => '1',
				'mode'          => 'sandbox',
				'client_id'     => 'cid',
				'client_secret' => 'sec',
				'webhook_id'    => '',
			)
		);

		$this->assertSame( 'incomplete', $status['state'], 'PayPal is webhook-only, so its webhook id stays required.' );
	}
}
