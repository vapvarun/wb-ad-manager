<?php
/**
 * Per-tick billing for unlimited-budget campaigns.
 *
 * A campaign with a budget is charged that budget at activation, so a billing
 * tick only records consumption. A campaign with budget=0 is never charged at
 * activation - there is no budget to reserve - so the tick is the only thing
 * that can collect. Billing_Manager used to skip the ledger for both, which
 * meant unlimited-budget CPM/CPC campaigns delivered indefinitely for free.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Wallet\Billing_Manager;

class Test_Unlimited_Budget_Billing extends Pro_Test_Case {

	/**
	 * Create an advertiser row bound to a funded user.
	 *
	 * The ledger stores integer minor units and Credits_Bridge::get_balance()
	 * converts to major on the way out, so the top-up is scaled here and every
	 * assertion below can talk in the same units the billing code does.
	 *
	 * @param float $credits Starting balance in major units.
	 * @return array{advertiser_id:int,user_id:int}
	 */
	private function make_funded_advertiser( $credits = 1000.0 ) {
		global $wpdb;

		$user_id = self::factory()->user->create();
		Factory::topup_user( $user_id, (int) round( $credits * 100 ) );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_advertisers',
			array(
				'user_id' => $user_id,
				'status'  => 'active',
			),
			array( '%d', '%s' )
		);

		return array(
			'advertiser_id' => (int) $wpdb->insert_id,
			'user_id'       => $user_id,
		);
	}

	/**
	 * Insert a campaign directly so the test controls budget/spent exactly,
	 * without going through activation's own charging.
	 *
	 * @param int   $advertiser_id Advertiser row id.
	 * @param float $budget        Campaign budget (0 = unlimited).
	 * @param float $spent         Accrued spend.
	 * @param float $billed        Already-billed amount.
	 * @return int Campaign id.
	 */
	private function make_campaign( $advertiser_id, $budget, $spent, $billed = 0.0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id'  => $advertiser_id,
				'name'           => 'Billing probe',
				'status'         => 'active',
				'pricing_model'  => 'cpm',
				'price_per_unit' => 5.0,
				'budget'         => $budget,
				'spent'          => $spent,
				'billed_amount'  => $billed,
			),
			array( '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * The bug. An unlimited-budget campaign with unbilled spend must actually
	 * take the credits, because nothing else ever will.
	 */
	public function test_unlimited_budget_campaign_is_charged_on_tick(): void {
		$who = $this->make_funded_advertiser( 1000.0 );
		$id  = $this->make_campaign( $who['advertiser_id'], 0.0, 120.0, 0.0 );

		$before = (float) Credits_Bridge::get_balance( $who['advertiser_id'] );

		$result = Billing_Manager::get_instance()->force_campaign_billing( $id );

		$this->assertFalse(
			is_wp_error( $result ),
			'Billing returned an error: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' )
		);
		$this->assertSame(
			120.0,
			round( $before - (float) Credits_Bridge::get_balance( $who['advertiser_id'] ), 2 ),
			'Unlimited-budget campaign delivered without debiting credits.'
		);
	}

	/**
	 * The other half. A budgeted campaign already paid its full budget at
	 * activation, so a tick must not take the money a second time.
	 */
	public function test_budgeted_campaign_is_not_charged_again_on_tick(): void {
		$who = $this->make_funded_advertiser( 1000.0 );
		$id  = $this->make_campaign( $who['advertiser_id'], 500.0, 120.0, 0.0 );

		$before = (float) Credits_Bridge::get_balance( $who['advertiser_id'] );

		Billing_Manager::get_instance()->force_campaign_billing( $id );

		$this->assertSame(
			$before,
			(float) Credits_Bridge::get_balance( $who['advertiser_id'] ),
			'A pre-charged campaign was billed twice.'
		);
	}

	/**
	 * Running out of credits must stop delivery, not silently accrue a debt the
	 * next tick can never collect either.
	 */
	public function test_insufficient_credits_pauses_the_campaign(): void {
		$who = $this->make_funded_advertiser( 10.0 );
		$id  = $this->make_campaign( $who['advertiser_id'], 0.0, 500.0, 0.0 );

		$result = Billing_Manager::get_instance()->force_campaign_billing( $id );

		$this->assertTrue( is_wp_error( $result ), 'Expected an error when credits run out.' );
		$this->assertSame( 'wbam_credits_insufficient', $result->get_error_code() );

		$campaign = Campaign_Manager::get_instance()->get( $id );
		$this->assertSame(
			'paused',
			$campaign->status,
			'Campaign kept delivering after the advertiser ran out of credits.'
		);
	}

	/**
	 * The rule both sides now share. This is the assertion that would have
	 * caught the original drift.
	 */
	public function test_needs_reservation_excludes_unlimited_budget(): void {
		$unlimited = (object) array(
			'pricing_model' => 'cpm',
			'budget'        => 0,
		);
		$budgeted  = (object) array(
			'pricing_model' => 'cpm',
			'budget'        => 500,
		);
		$flat      = (object) array(
			'pricing_model' => 'flat_rate',
			'budget'        => 500,
		);

		$this->assertFalse( Campaign_Manager::needs_reservation( $unlimited ) );
		$this->assertTrue( Campaign_Manager::needs_reservation( $budgeted ) );
		$this->assertFalse( Campaign_Manager::needs_reservation( $flat ) );
	}
}
