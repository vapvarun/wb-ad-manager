<?php
/**
 * Authorization on Pro's wbam_save_ad_meta consumer.
 *
 * save_pro_options() runs on the wbam_save_ad_meta action. That hook is public
 * API — free fires it from eight places, two of which authorize upstream rather
 * than at the firing site, and any third-party code can fire it too. A nonce
 * proves the request was intended, not that the caller may edit this post, so
 * the consumer authorizes against the specific post id it is handed.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Pro_Save_Authorization extends Pro_Test_Case {

	private function make_ad(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_title'  => 'Authorization fixture',
				'post_status' => 'publish',
			)
		);
	}

	public function tear_down(): void {
		unset( $_POST['wbam_pro_nonce'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * A subscriber holding a valid nonce must not be able to drive Pro's meta
	 * writer against someone else's ad.
	 */
	public function test_subscriber_with_valid_nonce_cannot_write_pro_meta(): void {
		$ad_id = $this->make_ad();
		update_post_meta( $ad_id, '_wbam_ab_testing', 'sentinel' );

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		// Nonces are per-user, so this is a nonce the subscriber genuinely holds.
		$_POST['wbam_pro_nonce'] = wp_create_nonce( 'wbam_pro_options' );

		do_action( 'wbam_save_ad_meta', $ad_id );

		$this->assertSame(
			'sentinel',
			get_post_meta( $ad_id, '_wbam_ab_testing', true ),
			'A subscriber drove Pro\'s save handler and mutated ad meta.'
		);
	}

	/**
	 * The same path must still work for a user who can edit the post, so the
	 * guard is shown to block the right callers rather than all of them.
	 */
	public function test_administrator_can_still_write_pro_meta(): void {
		$ad_id = $this->make_ad();
		update_post_meta( $ad_id, '_wbam_ab_testing', 'sentinel' );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_POST['wbam_pro_nonce'] = wp_create_nonce( 'wbam_pro_options' );

		do_action( 'wbam_save_ad_meta', $ad_id );

		// The handler deletes this stale key as its first act once authorized.
		$this->assertSame(
			'',
			get_post_meta( $ad_id, '_wbam_ab_testing', true ),
			'The guard blocked an administrator who should have been allowed through.'
		);
	}

	/**
	 * No nonce means no write, regardless of capability — the nonce check must
	 * not become dead code behind the new capability check.
	 */
	public function test_administrator_without_nonce_is_rejected(): void {
		$ad_id = $this->make_ad();
		update_post_meta( $ad_id, '_wbam_ab_testing', 'sentinel' );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		do_action( 'wbam_save_ad_meta', $ad_id );

		$this->assertSame(
			'sentinel',
			get_post_meta( $ad_id, '_wbam_ab_testing', true ),
			'The handler wrote without a nonce.'
		);
	}
}
