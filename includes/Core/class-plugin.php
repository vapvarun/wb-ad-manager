<?php
/**
 * Main Plugin Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Modules\Targeting\Targeting_Engine;
use WBAM\Modules\Targeting\Frequency_Manager;
use WBAM\Modules\Links\Links_Module;
use WBAM\Admin\Admin;
use WBAM\Admin\Settings;
use WBAM\Admin\Email_Captures;
use WBAM\Admin\Display_Options;
use WBAM\Admin\Setup_Wizard;
use WBAM\Admin\Demo_Data_Cleaner;
use WBAM\Admin\Help_Docs;
use WBAM\Admin\Upgrade_Pro;
use WBAM\Admin\First_Install_Pointers;
use WBAM\Admin\Field_Tooltips;
use WBAM\Admin\List_Empty_States;
use WBAM\Admin\Notice_Suppressor;
use WBAM\Frontend\Frontend;
use WBAM\API\API_Bootstrap;

/**
 * Plugin class.
 */
class Plugin {

	use Singleton;

	/**
	 * Placement engine instance.
	 *
	 * @var Placement_Engine
	 */
	private $placements;

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Frontend instance.
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Setup wizard instance.
	 *
	 * @var Setup_Wizard
	 */
	private $setup_wizard;

	/**
	 * Links module instance.
	 *
	 * @var Links_Module
	 */
	private $links;

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Check for database updates (for existing installations).
		$this->maybe_update_database();

		// Register post type on init hook (rewrite rules need this).
		add_action( 'init', array( $this, 'register_post_type' ), 5 );

		$this->init_components();
		$this->setup_hooks();

		do_action( 'wbam_init' );
	}

	/**
	 * Check and run database updates if needed.
	 *
	 * This ensures existing installations get new tables
	 * without requiring plugin deactivation/reactivation.
	 */
	private function maybe_update_database() {
		$installer = Installer::get_instance();

		if ( $installer->needs_update() ) {
			$installer->install();
		}
	}

	/**
	 * Initialize components.
	 */
	private function init_components() {
		// Placement -> accepted-formats map. Register the filter before
		// the Placement_Engine boots so every surface that reads the
		// wbam_get_placements registry sees the format metadata.
		Placement_Format_Map::register();

		// Placements engine.
		$this->placements = Placement_Engine::get_instance();
		$this->placements->init();

		// Frequency manager.
		$frequency = Frequency_Manager::get_instance();
		$frequency->init();

		// Admin.
		if ( is_admin() ) {
			$this->admin = Admin::get_instance();
			$this->admin->init();

			$this->settings = Settings::get_instance();
			$this->settings->init();

			// Email Captures — read/export/erase surface for the Email Capture
			// ad type's submissions (previously write-only; GDPR gap).
			( new Email_Captures() )->init();

			$display_options = Display_Options::get_instance();
			$display_options->init();

			// Setup wizard.
			$this->setup_wizard = new Setup_Wizard();
			$this->setup_wizard->init();

			// Demo data cleaner (Phase K) — one-click removal of rows
			// seeded by the setup wizard, with double-check against
			// the `_wbam_is_demo` post meta flag.
			$demo_cleaner = new Demo_Data_Cleaner();
			$demo_cleaner->register();

			// Help & Documentation.
			Help_Docs::get_instance();

			// Upgrade to PRO (only when PRO is not active).
			Upgrade_Pro::get_instance();

			// First-install WP-pointer tooltips (Phase G.2).
			// Class is flag-gated internally; safe to always register.
			$pointers = new First_Install_Pointers();
			$pointers->init();

			// Field-level tooltip popovers on the Ad edit screen (Phase G.4).
			$field_tooltips = new Field_Tooltips();
			$field_tooltips->init();

			// Friendly empty states for admin list screens (Phase G.5).
			$empty_states = new List_Empty_States();
			$empty_states->init();

			// Third-party admin-notice suppressor for WB Ad Manager screens.
			$notice_suppressor = new Notice_Suppressor();
			$notice_suppressor->init();
		}

		// Frontend.
		$this->frontend = Frontend::get_instance();
		$this->frontend->init();

		// BuddyPress module.
		if ( class_exists( 'BuddyPress' ) ) {
			$bp = new \WBAM\Modules\BuddyPress\BP_Module();
			$bp->init();
		}

		// bbPress module.
		if ( class_exists( 'bbPress' ) ) {
			$bbpress = new \WBAM\Modules\bbPress\bbPress_Module();
			$bbpress->init();
		}

		// Jetonomy module.
		if ( \WBAM\Modules\Jetonomy\Jetonomy_Module::is_jetonomy_active() ) {
			$jetonomy = new \WBAM\Modules\Jetonomy\Jetonomy_Module();
			$jetonomy->init();
		}

		// Links module.
		$this->links = Links_Module::get_instance();
		$this->links->init();

		// REST API — must load on both frontend and admin for rest_api_init to fire.
		new API_Bootstrap();

		// Abilities API (WP 6.9+) — registers categories and abilities on dedicated hooks.
		if ( function_exists( 'wp_register_ability' ) ) {
			new Abilities();
		}
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		add_action( 'admin_init', array( $this, 'activation_redirect' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_wbam_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		// Canonical registration for the shared toast/confirm toolkit
		// (`wbam-toast`). Hooked at init@1 so the handle exists in the
		// script/style registry before ANY request type — admin, frontend,
		// or AJAX — reaches a point where it, or WB Ad Manager Pro, needs
		// to enqueue it. Pro's advertiser portal and classifieds run on the
		// frontend, where `WBAM\Admin\Admin` (is_admin()-gated) never
		// loads, so registration lives here in the always-on bootstrap
		// instead. Every consumer calls `wp_enqueue_script( 'wbam-toast' )`
		// / `wp_enqueue_style( 'wbam-toast' )` with the bare handle — they
		// MUST NOT re-register.
		add_action( 'init', array( $this, 'register_shared_assets' ), 1 );

		// Invalidate the per-placement ad-count cache (Settings screen,
		// Task 7) on the same triggers Placement_Engine already uses to
		// invalidate its own placement cache — see
		// Placement_Engine::init(). Also covers untrashed_post: the
		// count query excludes trashed ads, so restoring one re-enters
		// it into the count and the cache must drop or the settings
		// screen undercounts for up to the 5-minute TTL.
		add_action( 'wbam_save_ad_meta', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
		add_action( 'delete_post', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
		add_action( 'trashed_post', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
		add_action( 'untrashed_post', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
	}

	/**
	 * Register the shared toast/confirm toolkit (script + style).
	 *
	 * Single source of truth for the `wbam-toast` handle — both this plugin
	 * and WB Ad Manager Pro depend on it. Mirrors the guard pattern in
	 * `Admin::enqueue_admin_tokens()`: registered (not enqueued) here so it
	 * exists everywhere, and each screen/shortcode that renders a confirm
	 * or toast opts in with a bare `wp_enqueue_script()` / `wp_enqueue_style()`
	 * call.
	 *
	 * @since 2.10.0
	 */
	public function register_shared_assets(): void {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( ! wp_script_is( 'wbam-toast', 'registered' ) ) {
			wp_register_script(
				'wbam-toast',
				WBAM_URL . 'assets/js/toast' . $suffix . '.js',
				array(),
				WBAM_VERSION,
				true
			);
		}

		if ( ! wp_style_is( 'wbam-toast', 'registered' ) ) {
			wp_register_style(
				'wbam-toast',
				WBAM_URL . 'assets/css/toast' . $suffix . '.css',
				array(),
				WBAM_VERSION
			);
		}
	}

	/**
	 * AJAX handler for dismissing admin notices permanently.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'wbam_dismiss_notice', 'nonce' );

		// The notices this dismisses only render on the wbam-ad screen, so the
		// audience is people who can edit ads. Match that.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$type    = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$allowed = array( 'bp', 'jetonomy' );
		if ( ! in_array( $type, $allowed, true ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'wbam_dismiss_' . $type . '_notice', 1 );
		wp_send_json_success();
	}

	/**
	 * Register custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Ads', 'Post Type General Name', 'wb-ads-rotator-with-split-test' ),
			'singular_name'      => _x( 'Ad', 'Post Type Singular Name', 'wb-ads-rotator-with-split-test' ),
			'menu_name'          => __( 'WB Ad Manager', 'wb-ads-rotator-with-split-test' ),
			'all_items'          => __( 'All Ads', 'wb-ads-rotator-with-split-test' ),
			'add_new_item'       => __( 'Add New Ad', 'wb-ads-rotator-with-split-test' ),
			'add_new'            => __( 'Add New', 'wb-ads-rotator-with-split-test' ),
			'new_item'           => __( 'New Ad', 'wb-ads-rotator-with-split-test' ),
			'edit_item'          => __( 'Edit Ad', 'wb-ads-rotator-with-split-test' ),
			'update_item'        => __( 'Update Ad', 'wb-ads-rotator-with-split-test' ),
			'view_item'          => __( 'View Ad', 'wb-ads-rotator-with-split-test' ),
			'search_items'       => __( 'Search Ads', 'wb-ads-rotator-with-split-test' ),
			'not_found'          => __( 'Not found', 'wb-ads-rotator-with-split-test' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'wb-ads-rotator-with-split-test' ),
		);

		$args = array(
			'label'               => __( 'Ad', 'wb-ads-rotator-with-split-test' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-megaphone',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => false,
		);

		register_post_type( 'wbam-ad', $args );
	}

	/**
	 * Activation redirect.
	 */
	public function activation_redirect() {
		if ( get_transient( '_wbam_activation_redirect' ) ) {
			delete_transient( '_wbam_activation_redirect' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No form data processed, just checking activation type.
			if ( ! isset( $_GET['activate-multi'] ) ) {
				// Redirect to setup wizard if not completed.
				if ( ! get_option( 'wbam_setup_complete' ) && ! get_option( 'wbam_setup_dismissed' ) ) {
					wp_safe_redirect( admin_url( 'index.php?page=wbam-setup' ) );
				} else {
					wp_safe_redirect( admin_url( 'edit.php?post_type=wbam-ad' ) );
				}
				exit;
			}
		}
	}

	/**
	 * Admin notices.
	 */
	public function admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! class_exists( 'BuddyPress' ) && ! get_user_meta( $user_id, 'wbam_dismiss_bp_notice', true ) ) {
			echo '<div class="notice notice-info is-dismissible" data-wbam-dismiss="bp"><p>';
			esc_html_e( 'BuddyPress is not active. BuddyPress activity placements are disabled.', 'wb-ads-rotator-with-split-test' );
			echo '</p></div>';
		}

		if ( ! \WBAM\Modules\Jetonomy\Jetonomy_Module::is_jetonomy_active() && ! get_user_meta( $user_id, 'wbam_dismiss_jetonomy_notice', true ) ) {
			echo '<div class="notice notice-info is-dismissible" data-wbam-dismiss="jetonomy"><p>';
			printf(
				/* translators: 1: opening link to Jetonomy store page, 2: closing link tag */
				esc_html__( 'Jetonomy support is ready. Install %1$sJetonomy%2$s to unlock seven new placement positions (sidebar, topic, and reply injection points).', 'wb-ads-rotator-with-split-test' ),
				'<a href="https://store.wbcomdesigns.com/jetonomy/" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			echo '</p></div>';
		}

		// Inline JS to persist dismissals via AJAX.
		?>
		<script>
		jQuery(function($){
			$('[data-wbam-dismiss]').on('click', '.notice-dismiss', function(){
				var type = $(this).closest('[data-wbam-dismiss]').data('wbam-dismiss');
				$.post(ajaxurl, { action: 'wbam_dismiss_notice', type: type, nonce: '<?php echo esc_js( wp_create_nonce( 'wbam_dismiss_notice' ) ); ?>' });
			});
		});
		</script>
		<?php
	}

	/**
	 * Get placements engine.
	 *
	 * @return Placement_Engine
	 */
	public function placements() {
		return $this->placements;
	}

	/**
	 * Get admin instance.
	 *
	 * @return Admin
	 */
	public function admin() {
		return $this->admin;
	}

	/**
	 * Get frontend instance.
	 *
	 * @return Frontend
	 */
	public function frontend() {
		return $this->frontend;
	}

	/**
	 * Get settings instance.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get links module instance.
	 *
	 * @return Links_Module
	 */
	public function links() {
		return $this->links;
	}
}
