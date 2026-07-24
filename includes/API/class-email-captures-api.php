<?php
/**
 * Email Captures REST controller — the API read surface for the
 * `wbam_email_submissions` table (Email Capture ad type).
 *
 * Completes the three-entry-points contract for the captures table: frontend
 * write (capture form) + admin read/export/erase (Email_Captures screen) + this
 * REST read. Admin-only.
 *
 * @package WBAM\API
 */

namespace WBAM\API;

use WBAM\Admin\Email_Captures;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wbam/v1/email-captures
 */
class Email_Captures_API {

	private const REST_NAMESPACE = 'wbam/v1';

	/**
	 * Hook route registration.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/email-captures',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_captures' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Only site owners may read captured PII.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /email-captures — paginated captures, newest first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_captures( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 200, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$screen = new Email_Captures();
		$total  = $screen->count();
		$rows   = $screen->get_page( $page, $per_page );

		$items = array_map(
			function ( $row ) {
				return array(
					'id'         => (int) $row->id,
					'ad_id'      => (int) $row->ad_id,
					'ad_title'   => $row->ad_id ? get_the_title( (int) $row->ad_id ) : '',
					'email'      => $row->email,
					'name'       => $row->name,
					'ip_address' => $row->ip_address,
					'created_at' => $row->created_at,
				);
			},
			$rows
		);

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}
}
