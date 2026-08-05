<?php
/**
 * Demo importer heals ownership on already-existing demo ads.
 *
 * Regression guard for Basecamp card 10163569807: demo ads created by an
 * older importer exist without `_wbam_advertiser_id`, and the importer's
 * already-exists path skipped them entirely — so re-running Import Demo
 * Data reported success while every advertiser portal stayed empty, with
 * no way to repair from Tools. heal_ad_ownership() now re-asserts both
 * ownership keys (the portal list reads the meta; edit/delete checks read
 * post_author), filling gaps without stomping deliberate reassignments.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Demo_Import_Ownership_Heal extends Pro_Test_Case {

	private function make_generator( array $advertiser_ids, array $user_ids ): object {
		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		$generator = new \WBAM_Demo_Data_Generator();

		foreach ( array( 'advertiser_ids' => $advertiser_ids, 'user_ids' => $user_ids ) as $prop => $value ) {
			$ref = new \ReflectionProperty( \WBAM_Demo_Data_Generator::class, $prop );
			$ref->setAccessible( true );
			$ref->setValue( $generator, $value );
		}

		return $generator;
	}

	private function heal( object $generator, int $post_id, int $index ): void {
		$method = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'heal_ad_ownership' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $generator, $post_id, $index );
		ob_end_clean();
	}

	public function test_unlinked_legacy_demo_ad_is_relinked(): void {
		$admin     = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$demo_user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Legacy state: demo ad authored by the importing admin, no meta.
		$ad = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_author' => $admin,
			)
		);

		$generator = $this->make_generator( array( 0 => 42 ), array( 0 => $demo_user ) );
		$this->heal( $generator, $ad, 0 );

		$this->assertSame( '42', (string) get_post_meta( $ad, '_wbam_advertiser_id', true ), 'Missing advertiser meta must be filled.' );
		$this->assertSame( $demo_user, (int) get_post_field( 'post_author', $ad ), 'Author must move to the mapped demo advertiser.' );
	}

	public function test_deliberate_reassignment_is_preserved(): void {
		$real_owner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$demo_user  = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$ad = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_author' => $real_owner,
			)
		);
		update_post_meta( $ad, '_wbam_advertiser_id', 7 );

		$generator = $this->make_generator( array( 0 => 42 ), array( 0 => $demo_user ) );
		$this->heal( $generator, $ad, 0 );

		$this->assertSame( '7', (string) get_post_meta( $ad, '_wbam_advertiser_id', true ), 'An existing advertiser assignment must not be overwritten.' );
	}
}
