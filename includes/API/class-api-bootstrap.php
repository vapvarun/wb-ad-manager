<?php
/**
 * API Bootstrap
 *
 * Loads all REST API classes for WB Ad Manager.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\API;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Bootstrap class.
 *
 * Instantiates all REST API handler classes so their constructors
 * can hook into rest_api_init and register their routes.
 */
class API_Bootstrap {

	/**
	 * Constructor — loads all API classes.
	 */
	public function __construct() {
		new Ads_API();
		new Analytics_API();
		new Links_API();
		new Settings_API();
	}
}
