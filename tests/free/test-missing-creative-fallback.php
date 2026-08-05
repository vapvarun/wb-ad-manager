<?php
/**
 * A broken creative loses its slot to a healthy competitor.
 *
 * Regression guard for Basecamp card 10167198418: an ENABLED image ad
 * whose creative produces no output (empty image_url, or the tracked
 * media-library attachment deleted) passed ad_is_renderable(), won the
 * weighted draw, rendered zero bytes, and blanked the slot while a
 * healthy filler sat idle - a silent revenue leak. The probe now
 * consults the type's cheap has_creative() check (no rendering), so
 * the fill-fallback pool-drain skips broken creatives the same way it
 * skips disabled ones.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Placement_Engine;

class Test_Missing_Creative_Fallback extends \WP_UnitTestCase {

	private function make_ad( string $title, string $type, array $data, array $extra = array() ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_ad_data', array_merge( array( 'type' => $type ), $data ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );
		foreach ( $extra as $key => $value ) {
			update_post_meta( $ad_id, $key, $value );
		}

		return $ad_id;
	}

	public function test_image_ad_without_a_creative_is_not_renderable(): void {
		$broken = $this->make_ad( 'Broken image winner', 'image', array( 'image_url' => '' ) );

		$this->assertFalse( Placement_Engine::get_instance()->ad_is_renderable( $broken ), 'An enabled ad that would render zero bytes must lose the slot, not blank it.' );
	}

	public function test_deleted_attachment_is_not_renderable(): void {
		$ghost = $this->make_ad(
			'Ghost attachment',
			'image',
			array( 'image_url' => 'https://example.com/gone.png' ),
			array( '_wbam_ad_image_id' => 999999 )
		);

		$this->assertFalse( Placement_Engine::get_instance()->ad_is_renderable( $ghost ), 'Deleting an image from the media library is the realistic trigger - the ad must report unhealthy.' );
	}

	public function test_healthy_ads_stay_renderable(): void {
		$engine = Placement_Engine::get_instance();

		$image = $this->make_ad( 'Healthy image', 'image', array( 'image_url' => 'https://example.com/live.png' ) );
		$code  = $this->make_ad( 'Healthy code', 'code', array( 'code' => '<span>ok</span>' ) );

		$this->assertTrue( $engine->ad_is_renderable( $image ), 'The working case must not regress.' );
		$this->assertTrue( $engine->ad_is_renderable( $code ), 'Types without a has_creative() probe are assumed healthy, exactly as before.' );
	}

	public function test_disabled_ad_still_not_renderable(): void {
		$disabled = $this->make_ad( 'Disabled ad', 'code', array( 'code' => '<span>x</span>' ) );
		update_post_meta( $disabled, '_wbam_enabled', '0' );

		$this->assertFalse( Placement_Engine::get_instance()->ad_is_renderable( $disabled ), 'The disabled-winner fallback path must not regress.' );
	}
}
