<?php
/**
 * Notice Suppressor
 *
 * Third-party admin notices (upgrade nags, unrelated review prompts, plugin
 * banners) make our own settings screens feel cluttered and also push our
 * real notices off-screen. On WB Ad Manager admin pages we strip everything
 * hooked to the notice actions EXCEPT our own callbacks and WordPress core.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notice_Suppressor class.
 */
class Notice_Suppressor {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'in_admin_header', array( $this, 'suppress_foreign_notices' ), 1 );
	}

	/**
	 * Strip foreign callbacks from the notice hooks on our screens.
	 */
	public function suppress_foreign_notices() {
		if ( ! $this->is_our_screen() ) {
			return;
		}

		$hooks = array(
			'admin_notices',
			'all_admin_notices',
			'user_admin_notices',
			'network_admin_notices',
		);

		foreach ( $hooks as $hook ) {
			$this->strip_hook( $hook );
		}
	}

	/**
	 * Is the current admin screen owned by WB Ad Manager?
	 *
	 * @return bool
	 */
	private function is_our_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// The wbam-ad CPT edit list, add-new, and single-post edit screens.
		if ( isset( $screen->post_type ) && 'wbam-ad' === $screen->post_type ) {
			return true;
		}

		// Subpages registered under the wbam-ad CPT (Settings, Upgrade, Help…).
		if ( ! empty( $screen->id ) && false !== strpos( $screen->id, 'wbam-ad_page_' ) ) {
			return true;
		}

		// Setup wizard (registered under Dashboard).
		if ( ! empty( $screen->id ) && 'dashboard_page_wbam-setup' === $screen->id ) {
			return true;
		}

		// Belt-and-suspenders: any admin page whose `page` query is a wbam-*.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && 0 === strpos( $page, 'wbam-' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove every callback on $hook whose owner is not WB Ad Manager.
	 *
	 * Callbacks we keep:
	 *   - Any class method whose class is in the `WBAM\` namespace.
	 *   - Any function whose name starts with `wbam_`.
	 *
	 * Everything else (third-party plugins, themes, rogue closures) is pulled.
	 *
	 * @param string $hook Hook name.
	 */
	private function strip_hook( $hook ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return;
		}

		$filter = $wp_filter[ $hook ];
		if ( ! is_object( $filter ) || empty( $filter->callbacks ) ) {
			return;
		}

		foreach ( $filter->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$callable = isset( $cb['function'] ) ? $cb['function'] : null;
				if ( null === $callable ) {
					continue;
				}

				if ( $this->is_our_callback( $callable ) ) {
					continue;
				}

				remove_action( $hook, $callable, $priority );
			}
		}
	}

	/**
	 * Does the given callback belong to WB Ad Manager?
	 *
	 * @param mixed $callable A WP hook callable: string, array, or Closure.
	 * @return bool
	 */
	private function is_our_callback( $callable ) {
		// [ $object, 'method' ] or [ 'ClassName', 'method' ].
		if ( is_array( $callable ) && isset( $callable[0] ) ) {
			$class = is_object( $callable[0] ) ? get_class( $callable[0] ) : (string) $callable[0];
			return 0 === strpos( $class, 'WBAM\\' );
		}

		// Plain function name or static "Class::method".
		if ( is_string( $callable ) ) {
			if ( 0 === strpos( $callable, 'WBAM\\' ) ) {
				return true;
			}
			if ( 0 === strpos( $callable, 'wbam_' ) ) {
				return true;
			}
			return false;
		}

		// Closures — only keep if declared inside a WBAM file.
		if ( $callable instanceof \Closure ) {
			try {
				$ref  = new \ReflectionFunction( $callable );
				$file = (string) $ref->getFileName();
				if ( '' !== $file && false !== strpos( $file, DIRECTORY_SEPARATOR . 'wb-ads-rotator-with-split-test' . DIRECTORY_SEPARATOR ) ) {
					return true;
				}
			} catch ( \ReflectionException $e ) {
				return false;
			}
		}

		return false;
	}
}
