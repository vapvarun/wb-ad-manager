<?php
/**
 * Pro cron schedules are registered.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Cron_Registration extends Pro_Test_Case {

	/**
	 * @dataProvider expected_hooks
	 */
	public function test_cron_hook_has_a_listener( string $hook ): void {
		$this->assertNotFalse(
			has_action( $hook ),
			"Cron hook {$hook} must have at least one listener"
		);
	}

	public function expected_hooks(): array {
		return array(
			array( 'wbam_pro_daily_aggregation' ),
			array( 'wbam_pro_hourly_billing' ),
			array( 'wbam_check_campaign_budgets' ),
			array( 'wbam_expire_classifieds' ),
			array( 'wbam_expire_upgrades' ),
			array( 'wbam_cleanup_audit_log' ),
		);
	}
}
