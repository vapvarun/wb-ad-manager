<?php
/**
 * REST route shape contract — guards against bug #9789216090
 * (analytics dashboard empty — endpoint returning 500 or wrong shape).
 *
 * We only assert shape for empty-DB responses. A richer content assertion
 * should live behind fixtures.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;
use WP_REST_Request;

class Test_Rest_Shapes extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Boot REST server so routes register.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		// Routes require an authenticated user with manage privileges.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * @dataProvider rest_routes
	 */
	public function test_route_returns_non_500( string $route ): void {
		$response = rest_do_request( new WP_REST_Request( 'GET', $route ) );
		$this->assertLessThan( 500, $response->get_status(), "Route {$route} must not 500" );
	}

	public function rest_routes(): array {
		return array(
			array( '/wbam/v1/analytics/overview' ),
			array( '/wbam/v1/analytics/daily' ),
			array( '/wbam/v1/ads' ),
			array( '/wbam/v1/links' ),
			array( '/wbam/v1/settings' ),
		);
	}

	public function test_analytics_overview_shape_on_empty_db(): void {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wbam/v1/analytics/overview' ) );
		$data     = $response->get_data();
		$this->assertIsArray( $data, 'analytics/overview must return an array/object payload' );
	}
}
