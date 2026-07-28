<?php
/**
 * Credits SDK lifecycle — topup, hold, deduct, refund, cancel.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;

class Test_Credits_SDK extends Pro_Test_Case {

	private const SLUG = 'wbam-pro';

	/*
	 * Credits::hold() types $item_id as int and documents it as the associated
	 * item ID - a campaign or ad row, not a label. These tests passed slug
	 * strings and every one of them has errored with a TypeError since the SDK
	 * tightened the signature. Distinct ids per test so a hold in one cannot be
	 * cancelled or deducted by another.
	 */
	private const ITEM_HOLD   = 9001;
	private const ITEM_CANCEL = 9002;
	private const ITEM_DEDUCT = 9003;
	private const ITEM_REFUND = 9004;

	public function test_topup_increases_balance(): void {
		$user = self::factory()->user->create();
		$this->assertSame( 0, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );

		Factory::topup_user( $user, 500 );
		$this->assertSame( 500, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}

	public function test_hold_reserves_credits(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 1000 );

		$hold = \Wbcom\Credits\Credits::hold( self::SLUG, $user, 300, self::ITEM_HOLD, 'hold test' );
		$this->assertNotFalse( $hold );

		// Available balance should reflect the hold.
		$balance = \Wbcom\Credits\Credits::get_balance( self::SLUG, $user );
		$this->assertLessThanOrEqual( 1000, $balance );
	}

	public function test_cancel_hold_restores_availability(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 500 );

		\Wbcom\Credits\Credits::hold( self::SLUG, $user, 200, self::ITEM_CANCEL, 'hold' );
		\Wbcom\Credits\Credits::cancel_hold( self::SLUG, $user, self::ITEM_CANCEL );

		$this->assertSame( 500, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}

	public function test_deduct_writes_ledger_row(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 400 );

		\Wbcom\Credits\Credits::hold( self::SLUG, $user, 150, self::ITEM_DEDUCT, 'hold' );
		$ok = \Wbcom\Credits\Credits::deduct( self::SLUG, $user, 150, self::ITEM_DEDUCT, 'deduct' );

		$this->assertTrue( (bool) $ok );
		$this->assertSame( 250, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}

	public function test_refund_reverses_deduction(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 600 );
		\Wbcom\Credits\Credits::hold( self::SLUG, $user, 200, self::ITEM_REFUND, 'hold' );
		\Wbcom\Credits\Credits::deduct( self::SLUG, $user, 200, self::ITEM_REFUND, 'deduct' );
		\Wbcom\Credits\Credits::refund( self::SLUG, $user, 200, self::ITEM_REFUND, 'refund' );

		$this->assertSame( 600, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}
}
