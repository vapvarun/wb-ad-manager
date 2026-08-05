<?php
/**
 * Rich Content ads render regardless of stored type spelling.
 *
 * Regression guard for Basecamp card 10165983729: the handler registers
 * as 'rich-content' (hyphen) while the setup wizard, admin preview, and
 * abilities API wrote 'rich_content' (underscore) - so the engine's
 * lookup missed and every Rich Content ad, including the sample every
 * fresh install ships, rendered as an empty string, silently. The
 * lookup now normalizes the legacy spellings (same alias set PRO's
 * migration folds) and the writers use the canonical id.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Placement_Engine;

class Test_Rich_Content_Ad_Type extends \WP_UnitTestCase {

	private function make_ad( string $stored_type ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_title'  => 'Rich guard ' . $stored_type,
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type'    => $stored_type,
				'content' => '<div class="rich-guard-body">Pro Tip body</div>',
			)
		);
		update_post_meta( $ad_id, '_wbam_placements', array( 'after_paragraph' ) );

		return $ad_id;
	}

	/**
	 * @dataProvider stored_spellings
	 */
	public function test_stored_spelling_renders( string $stored_type ): void {
		$ad_id  = $this->make_ad( $stored_type );
		$output = Placement_Engine::get_instance()->render_ad(
			$ad_id,
			array(
				'placement'       => 'after_paragraph',
				'allow_duplicate' => true,
			)
		);

		$this->assertStringContainsString( 'Pro Tip body', $output, "An ad stored as '{$stored_type}' must render - a silent empty string killed the whole creative type." );
	}

	public function stored_spellings(): array {
		return array(
			'canonical hyphen'   => array( 'rich-content' ),
			'legacy underscore'  => array( 'rich_content' ),
		);
	}

	public function test_registry_and_wizard_agree_on_the_id(): void {
		$registered = array_keys( Placement_Engine::get_instance()->get_ad_types() );

		$this->assertContains( 'rich-content', $registered );
		// The wizard's sample ad must use an id the registry resolves.
		$wizard_source = (string) file_get_contents( WBAM_PATH . 'includes/Admin/class-setup-wizard.php' );
		$this->assertStringNotContainsString( "'rich_content'", $wizard_source, 'Writers use the canonical registered id.' );
	}
}
