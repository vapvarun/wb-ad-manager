<?php
/**
 * Link edit screen respects the per-link cloaking toggle.
 *
 * Regression guard for Basecamp card 10128201344: with "Enable
 * cloaking" unchecked, the edit page still advertised the /go/ cloaked
 * URL — in the "ready to use" notice (with copy and test buttons for a
 * URL that is not served) and under a hardcoded "Cloaked URL" label in
 * Link Information. With cloaking off, the notice now offers only the
 * shortcode and Link Information labels the value it actually shows.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Link_Manager;
use WBAM\Modules\Links\Links_Admin;

class Test_Links_Cloaking_Display extends \WP_UnitTestCase {

	private function render_edit_screen( int $link_id ): string {
		$_GET['action']  = 'edit';
		$_GET['link_id'] = (string) $link_id;

		$admin  = new Links_Admin();
		$method = new \ReflectionMethod( Links_Admin::class, 'render_edit_form' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $admin );
		$html = (string) ob_get_clean();

		unset( $_GET['action'], $_GET['link_id'] );

		return $html;
	}

	private function make_link( bool $cloaking ): object {
		$link = Link_Manager::get_instance()->create(
			array(
				'name'             => 'Cloaking Display ' . ( $cloaking ? 'On' : 'Off' ),
				'url'              => 'https://example.com/destination?tag=x',
				'slug'             => $cloaking ? 'cloak-on' : 'cloak-off',
				'cloaking_enabled' => $cloaking ? 1 : 0,
			)
		);
		$this->assertNotWPError( $link );

		return is_object( $link ) ? $link : Link_Manager::get_instance()->get( (int) $link );
	}

	public function test_disabled_cloaking_shows_no_cloaked_url(): void {
		$link = $this->make_link( false );
		$html = $this->render_edit_screen( (int) $link->id );

		$cloaked = home_url( '/' . $link->get_cloak_prefix() . '/' . $link->slug );

		$this->assertStringNotContainsString( $cloaked, $html, 'A cloaked URL that is not served must not be advertised anywhere on the page.' );
		$this->assertStringContainsString( 'Destination URL', $html );
		$this->assertStringNotContainsString( '1. Cloaked URL', $html );
	}

	public function test_enabled_cloaking_still_offers_both_options(): void {
		$link = $this->make_link( true );
		$html = $this->render_edit_screen( (int) $link->id );

		$cloaked = home_url( '/' . $link->get_cloak_prefix() . '/' . $link->slug );

		$this->assertStringContainsString( $cloaked, $html );
		$this->assertStringContainsString( '1. Cloaked URL', $html );
		$this->assertStringContainsString( '2. Shortcode', $html );
	}
}
