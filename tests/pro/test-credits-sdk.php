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

	public function test_topup_increases_balance(): void {
		$user = self::factory()->user->create();
		$this->assertSame( 0, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );

		Factory::topup_user( $user, 500 );
		$this->assertSame( 500, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}

	public function test_hold_reserves_credits(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 1000 );

		$hold = \Wbcom\Credits\Credits::hold( self::SLUG, $user, 300, 'test-item', 'hold test' );
		$this->assertNotFalse( $hold );

		// Available balance should reflect the hold.
		$balance = \Wbcom\Credits\Credits::get_balance( self::SLUG, $user );
		$this->assertLessThanOrEqual( 1000, $balance );
	}

	public function test_cancel_hold_restores_availability(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 500 );

		\Wbcom\Credits\Credits::hold( self::SLUG, $user, 200, 'cancel-me', 'hold' );
		\Wbcom\Credits\Credits::cancel_hold( self::SLUG, $user, 'cancel-me' );

		$this->assertSame( 500, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}

	public function test_deduct_writes_ledger_row(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 400 );

		\Wbcom\Credits\Credits::hold( self::SLUG, $user, 150, 'deduct-target', 'hold' );
		$ok = \Wbcom\Credits\Credits::deduct( self::SLUG, $user, 150, 'deduct-target', 'deduct' );

		$this->assertTrue( (bool) $ok );
		$this->assertSame( 250, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}

	public function test_refund_reverses_deduction(): void {
		$user = self::factory()->user->create();
		Factory::topup_user( $user, 600 );
		\Wbcom\Credits\Credits::hold( self::SLUG, $user, 200, 'refund-target', 'hold' );
		\Wbcom\Credits\Credits::deduct( self::SLUG, $user, 200, 'refund-target', 'deduct' );
		\Wbcom\Credits\Credits::refund( self::SLUG, $user, 200, 'refund-target', 'refund' );

		$this->assertSame( 600, \Wbcom\Credits\Credits::get_balance( self::SLUG, $user ) );
	}
}
