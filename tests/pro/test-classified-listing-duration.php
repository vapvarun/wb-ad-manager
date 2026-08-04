<?php
/**
 * The "Listing duration (days)" setting actually decides expiry.
 *
 * Regression guard for Basecamp card 10163938441: the setting was
 * written by the admin form and read by nothing — expiry came from the
 * selected package or a hardcoded 30, so the owner's most prominent
 * classifieds control silently did nothing. The setting is now the
 * fallback that hardcoded 30 pretended to be; a package duration still
 * wins when it defines one.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Listing_Duration extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['listing_duration'] = 60;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );
	}

	private function make_classified( array $overrides = array() ): object {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$classified = Classified_Manager::get_instance()->create(
			array_merge(
				array(
					'title'         => 'Duration listing',
					'description'   => 'Duration test.',
					'advertiser_id' => $advertiser->id,
					'status'        => 'pending',
				),
				$overrides
			)
		);
		$this->assertNotWPError( $classified );

		return $classified;
	}

	private function days_until_expiry( object $classified ): int {
		return (int) round( ( strtotime( $classified->expires_at ) - time() ) / DAY_IN_SECONDS );
	}

	public function test_owner_setting_decides_expiry_when_no_package_duration(): void {
		$classified = $this->make_classified();

		$this->assertSame( 60, $this->days_until_expiry( $classified ), 'With no package duration, the owner\'s listing_duration setting must decide expiry - not a hardcoded 30.' );
	}

	public function test_package_duration_still_wins(): void {
		$classified = $this->make_classified( array( 'duration_days' => 14 ) );

		$this->assertSame( 14, $this->days_until_expiry( $classified ) );
	}
}
