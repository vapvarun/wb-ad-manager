<?php
/**
 * Settings Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;

/**
 * Settings class.
 */
class Settings {

	use Singleton;

	/**
	 * Option name.
	 */
	const OPTION_NAME = 'wbam_settings';

	/**
	 * Default settings.
	 *
	 * These defaults are optimized for first-time users:
	 * - Ads visible to admins so they can test immediately
	 * - Performance features enabled for better page load
	 * - Ad label set for transparency/compliance
	 *
	 * @var array
	 */
	private $defaults = array(
		'disable_ads_logged_in'    => false,
		'disable_ads_admin'        => false,  // Show ads to admins so they can test.
		'ad_label'                 => 'Advertisement',
		'ad_label_position'        => 'above',
		'container_class'          => '',
		'disable_on_post_types'    => array(),
		'max_ads_per_page'         => 10,     // Sensible limit to prevent ad overload.
		'geo_primary_provider'     => 'ip-api',
		'geo_ipinfo_key'           => '',
		'adsense_publisher_id'     => '',
		'adsense_auto_ads'         => false,
		'require_consent_adsense'  => false,  // Require consent before loading AdSense.
		'anonymize_ip'             => true,   // Anonymize IP addresses in stored data.
		'delete_data_on_uninstall' => false,
		// Link cloaking. Read at runtime by Link_Cloaker but previously had no
		// UI (the wbam_settings_tabs/_fields filter framework was never applied),
		// so the cloak prefix and inactive-link behaviour were unconfigurable.
		'link_cloak_prefix'        => 'go',
		'link_inactive_action'     => '404',
		'link_inactive_url'        => '',
		// Placement gates. Empty array means ALL placements — never "none".
		// See Settings_Helper::enabled_placements().
		'enabled_placements'       => array(),
		'advertiser_placements'    => array(),
	);

	/**
	 * Initialize.
	 */
	public function init() {
		// Use priority 25 so Settings appears under PRO's Settings section header (priority 20) when PRO is active.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 25 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add submenu page.
	 */
	public function add_menu() {
		// Standalone, this IS the settings screen, so it is simply "Settings".
		// With PRO active there are two config screens, so name this one for
		// what it actually holds - how ads render on the site - while PRO's
		// carries the business configuration under "Settings". A site owner can
		// then tell them apart without opening both.
		$pro_active = defined( 'WBAM_PRO_VERSION' );
		$title      = $pro_active
			? __( 'Ad Display', 'wb-ads-rotator-with-split-test' )
			: __( 'Settings', 'wb-ads-rotator-with-split-test' );

		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			$title,
			$title,
			'manage_options',
			'wbam-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'wbam_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		// Features Section - which parts of the plugin this site uses.
		//
		// PRO lists these same modules on its Modules tab alongside its own, so
		// the site owner has one Modules screen rather than one per plugin.
		// This section is therefore only rendered when PRO is absent. The
		// stored value lives here either way, so the toggle keeps working if
		// PRO is later deactivated.
		if ( ! defined( 'WBAM_PRO_VERSION' ) ) {
			$this->register_feature_settings();
		}

		// General Section.
		$this->register_general_settings();
	}

	/**
	 * Register the Features section (FREE-only installs).
	 *
	 * @since 2.9.2
	 */
	private function register_feature_settings() {
		add_settings_section(
			'wbam_features',
			__( 'Features', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_features_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'module_links',
			__( 'Link Manager', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_module_field' ),
			'wbam-settings',
			'wbam_features',
			array(
				'id'          => 'links',
				'label'       => __( 'Enable the Link Manager', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Adds the Links menu for managing outbound and affiliate links, link categories and partnership inquiries. Turn this off if you only run ads - your existing link data is kept and reappears if you switch it back on.', 'wb-ads-rotator-with-split-test' ),
			)
		);
	}

	/**
	 * Register every settings section other than Features.
	 *
	 * @since 2.9.2
	 */
	private function register_general_settings() {
		// General Section.
		add_settings_section(
			'wbam_general',
			__( 'General Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_general_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'disable_ads_logged_in',
			__( 'Disable for Logged-in Users', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_ads_logged_in',
				'description' => __( 'Hide ads for logged-in users.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'disable_ads_admin',
			__( 'Disable for Admins', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_ads_admin',
				'description' => __( 'Hide ads for administrators.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'disable_on_post_types',
			__( 'Disable on Post Types', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_post_types_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_on_post_types',
				'description' => __( 'Select post types where ads should be disabled.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'max_ads_per_page',
			__( 'Maximum Ads Per Page', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_number_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'label_for'   => 'wbam_setting_max_ads_per_page',
				'id'          => 'max_ads_per_page',
				'description' => __( 'Maximum number of ads to show per page. Set 0 for unlimited.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Display Section.
		add_settings_section(
			'wbam_display',
			__( 'Display Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_display_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'ad_label',
			__( 'Ad Label Text', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'label_for'   => 'wbam_setting_ad_label',
				'id'          => 'ad_label',
				'placeholder' => __( 'e.g., Advertisement', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Optional label to display above/below ads.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'ad_label_position',
			__( 'Label Position', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_select_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'label_for' => 'wbam_setting_ad_label_position',
				'id'        => 'ad_label_position',
				'options'   => array(
					'above' => __( 'Above Ad', 'wb-ads-rotator-with-split-test' ),
					'below' => __( 'Below Ad', 'wb-ads-rotator-with-split-test' ),
				),
			)
		);

		add_settings_field(
			'container_class',
			__( 'Custom Container Class', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'label_for'   => 'wbam_setting_container_class',
				'id'          => 'container_class',
				'placeholder' => __( 'e.g., my-ad-wrapper', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Additional CSS class for ad containers.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Placements Section.
		add_settings_section(
			'wbam_placements',
			__( 'Placements', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_placements_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'placement_gates',
			__( 'Available Slots', 'wb-ads-rotator-with-split-test' ),
			array( '\WBAM\Admin\Placement_Settings', 'render_table' ),
			'wbam-settings',
			'wbam_placements'
		);

		// Geo Targeting Section.
		add_settings_section(
			'wbam_geo',
			__( 'Geo Targeting', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_geo_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'geo_primary_provider',
			__( 'Primary Provider', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_geo_provider_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'id' => 'geo_primary_provider',
			)
		);

		add_settings_field(
			'geo_ipinfo_key',
			__( 'ipinfo.io API Key', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'label_for'   => 'wbam_setting_geo_ipinfo_key',
				'id'          => 'geo_ipinfo_key',
				'placeholder' => __( 'Enter API key (optional)', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Get a free API key from ipinfo.io for 50K requests/month.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// AdSense Section.
		add_settings_section(
			'wbam_adsense',
			__( 'Google AdSense', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_adsense_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'adsense_publisher_id',
			__( 'Publisher ID', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'label_for'   => 'wbam_setting_adsense_publisher_id',
				'id'          => 'adsense_publisher_id',
				'placeholder' => 'ca-pub-1234567890123456',
				'description' => __( 'Your AdSense Publisher ID (e.g., ca-pub-1234567890123456). Used as default for all AdSense ads.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'adsense_auto_ads',
			__( 'Auto Ads', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'id'          => 'adsense_auto_ads',
				'description' => __( 'Enable AdSense Auto Ads on your site. Google will automatically place ads.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Privacy Section.
		add_settings_section(
			'wbam_privacy',
			__( 'Privacy & GDPR', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_privacy_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'require_consent_adsense',
			__( 'Require Consent for AdSense', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_privacy',
			array(
				'id'          => 'require_consent_adsense',
				'description' => __( 'Only load AdSense scripts after user consent. Works with Cookie Notice, CookieYes, Complianz, and other consent plugins.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'anonymize_ip',
			__( 'Anonymize IP Addresses', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_privacy',
			array(
				'id'          => 'anonymize_ip',
				'description' => __( 'Store anonymized IP hashes instead of raw IP addresses. Recommended for GDPR compliance.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Advanced Section.
		add_settings_section(
			'wbam_advanced',
			__( 'Advanced', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_advanced_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete Data on Uninstall', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_advanced',
			array(
				'id'          => 'delete_data_on_uninstall',
				'description' => __( 'Delete all plugin data (ads, analytics, settings) when the plugin is uninstalled.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Link Cloaking Section. These three keys are read at runtime by
		// Link_Cloaker; they used to be defined in a wbam_settings_fields filter
		// that nothing ever rendered, so they were unconfigurable.
		add_settings_section(
			'wbam_links',
			__( 'Link Cloaking', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_links_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'link_cloak_prefix',
			__( 'Cloak Prefix', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_links',
			array(
				'label_for'   => 'wbam_setting_link_cloak_prefix',
				'id'          => 'link_cloak_prefix',
				'placeholder' => 'go',
				/* translators: %s: example cloaked URL */
				'description' => sprintf( __( 'URL prefix for cloaked links, e.g. %s. Rewrite rules refresh automatically on change.', 'wb-ads-rotator-with-split-test' ), home_url( '/go/your-link' ) ),
			)
		);

		add_settings_field(
			'link_inactive_action',
			__( 'Inactive Link Action', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_select_field' ),
			'wbam-settings',
			'wbam_links',
			array(
				'label_for'   => 'wbam_setting_link_inactive_action',
				'id'          => 'link_inactive_action',
				'options'     => array(
					'404'    => __( 'Show 404 page', 'wb-ads-rotator-with-split-test' ),
					'home'   => __( 'Redirect to homepage', 'wb-ads-rotator-with-split-test' ),
					'custom' => __( 'Redirect to custom URL', 'wb-ads-rotator-with-split-test' ),
				),
				'description' => __( 'What happens when an inactive or expired cloaked link is accessed.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'link_inactive_url',
			__( 'Inactive Link URL', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_links',
			array(
				'label_for'   => 'wbam_setting_link_inactive_url',
				'id'          => 'link_inactive_url',
				'placeholder' => 'https://example.com/gone',
				'description' => __( 'Used only when the action above is "Redirect to custom URL".', 'wb-ads-rotator-with-split-test' ),
			)
		);
	}

	/**
	 * Render the Link Cloaking section intro.
	 */
	public function render_links_section() {
		echo '<p>' . esc_html__( 'Control how cloaked link URLs look and how inactive links behave.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, $this->defaults );
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get( $key, $default_value = null ) {
		$settings = $this->get_settings();
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		return null !== $default_value ? $default_value : ( isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Module toggles. This method rebuilds the option from scratch, so any
		// key not written here is dropped on save - modules must be explicit or
		// the toggle would reset itself every time Settings is saved. An
		// unchecked box posts nothing, which correctly resolves to false.
		$sanitized['modules'] = array();
		$posted_modules       = isset( $input['modules'] ) && is_array( $input['modules'] ) ? $input['modules'] : array();
		foreach ( array_keys( \WBAM\Core\Settings_Helper::module_defaults() ) as $module_slug ) {
			$sanitized['modules'][ $module_slug ] = ! empty( $posted_modules[ $module_slug ] );
		}

		$sanitized['disable_ads_logged_in'] = ! empty( $input['disable_ads_logged_in'] );
		$sanitized['disable_ads_admin']     = ! empty( $input['disable_ads_admin'] );
		$sanitized['ad_label']              = sanitize_text_field( $input['ad_label'] ?? '' );
		$sanitized['ad_label_position']     = in_array( $input['ad_label_position'] ?? '', array( 'above', 'below' ), true ) ? $input['ad_label_position'] : 'above';
		$sanitized['container_class']       = sanitize_html_class( $input['container_class'] ?? '' );
		$sanitized['max_ads_per_page']      = absint( $input['max_ads_per_page'] ?? 0 );

		// Link cloaking. sanitize_title keeps the prefix rewrite-safe (matches
		// Link_Cloaker::get_cloak_prefix); default back to 'go' if emptied.
		$cloak_prefix                      = sanitize_title( $input['link_cloak_prefix'] ?? '' );
		$sanitized['link_cloak_prefix']    = '' !== $cloak_prefix ? $cloak_prefix : 'go';
		$sanitized['link_inactive_action'] = in_array( $input['link_inactive_action'] ?? '', array( '404', 'home', 'custom' ), true ) ? $input['link_inactive_action'] : '404';
		$sanitized['link_inactive_url']    = esc_url_raw( $input['link_inactive_url'] ?? '' );

		if ( ! empty( $input['disable_on_post_types'] ) && is_array( $input['disable_on_post_types'] ) ) {
			$sanitized['disable_on_post_types'] = array_map( 'sanitize_key', $input['disable_on_post_types'] );
		} else {
			$sanitized['disable_on_post_types'] = array();
		}

		// Geo targeting settings.
		$valid_providers                   = array( 'ip-api', 'ipinfo', 'ipapi-co' );
		$geo_provider                      = isset( $input['geo_primary_provider'] ) ? sanitize_key( $input['geo_primary_provider'] ) : 'ip-api';
		$sanitized['geo_primary_provider'] = in_array( $geo_provider, $valid_providers, true ) ? $geo_provider : 'ip-api';
		$sanitized['geo_ipinfo_key']       = sanitize_text_field( $input['geo_ipinfo_key'] ?? '' );

		// AdSense settings.
		$sanitized['adsense_publisher_id'] = sanitize_text_field( $input['adsense_publisher_id'] ?? '' );
		$sanitized['adsense_auto_ads']     = ! empty( $input['adsense_auto_ads'] );

		// Privacy settings.
		$sanitized['require_consent_adsense'] = ! empty( $input['require_consent_adsense'] );
		$sanitized['anonymize_ip']            = ! empty( $input['anonymize_ip'] );

		// Advanced settings.
		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		// Placement gates. sanitize_settings() rebuilds the option from
		// scratch, so these must be written explicitly or every save on
		// this screen would wipe them.
		$sanitized['enabled_placements'] = ( ! empty( $input['enabled_placements'] ) && is_array( $input['enabled_placements'] ) )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $input['enabled_placements'] ) ) ) )
			: array();

		$advertiser = ( ! empty( $input['advertiser_placements'] ) && is_array( $input['advertiser_placements'] ) )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $input['advertiser_placements'] ) ) ) )
			: array();

		// A slot closed at site level can never be sellable, whatever the
		// client posted. Enforced server-side; the JS coupling in
		// admin-placement-settings.js is a convenience, not the guard.
		if ( ! empty( $advertiser ) && ! empty( $sanitized['enabled_placements'] ) ) {
			$advertiser = array_values( array_intersect( $advertiser, $sanitized['enabled_placements'] ) );
		}

		$sanitized['advertiser_placements'] = $advertiser;

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP Settings API pattern, nonce verified by options.php.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'wbam_messages', 'wbam_message', __( 'Settings saved.', 'wb-ads-rotator-with-split-test' ), 'updated' );
		}
		?>
		<div class="wrap wbam-admin wbam-settings-page wbam-settings-wrap">
			<?php
			\WBAM\Admin\UX::page_header(
				array(
					'title' => get_admin_page_title(),
					'desc'  => __( 'Ad display, geo-targeting, privacy, link cloaking and more.', 'wb-ads-rotator-with-split-test' ),
				)
			);
			settings_errors( 'wbam_messages' );
			?>

			<div class="wbam-settings-container">
				<form action="options.php" method="post" class="wbam-settings-form">
					<?php
					settings_fields( 'wbam_settings_group' );
					do_settings_sections( 'wbam-settings' );
					submit_button( __( 'Save Settings', 'wb-ads-rotator-with-split-test' ) );
					?>
				</form>

				<div class="wbam-settings-sidebar">
					<div class="wbam-sidebar-box">
						<h3><?php esc_html_e( 'Quick Links', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<ul>
							<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>"><?php esc_html_e( 'All Ads', 'wb-ads-rotator-with-split-test' ); ?></a></li>
							<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>"><?php esc_html_e( 'Add New Ad', 'wb-ads-rotator-with-split-test' ); ?></a></li>
						</ul>
					</div>

					<div class="wbam-sidebar-box">
						<h3><?php esc_html_e( 'Shortcodes', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><code>[wbam_ad id="123"]</code></p>
						<p class="description"><?php esc_html_e( 'Display a single ad by ID.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<p><code>[wbam_ads ids="1,2,3"]</code></p>
						<p class="description"><?php esc_html_e( 'Display multiple ads.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render general section.
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general ad display settings.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render display section.
	 */
	public function render_display_section() {
		echo '<p>' . esc_html__( 'Customize how ads appear on your site.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Placements section description.
	 */
	public function render_placements_section(): void {
		echo '<p>' . esc_html__(
			'Choose which slots this site uses, and which of those advertisers may buy. Unticking Site stops ads rendering in that slot. Unticking Advertisers only removes it from the advertiser portal — creatives already assigned keep running.',
			'wb-ads-rotator-with-split-test'
		) . '</p>';
	}

	/**
	 * Render geo section.
	 */
	public function render_geo_section() {
		echo '<p>' . esc_html__( 'Configure IP geolocation providers for geo-targeting. The system will try providers in order until one succeeds.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render AdSense section.
	 */
	public function render_adsense_section() {
		echo '<p>' . esc_html__( 'Configure Google AdSense integration. The AdSense script will only be loaded once, even with multiple ad units on a page.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render privacy section.
	 */
	public function render_privacy_section() {
		echo '<p>' . esc_html__( 'Configure privacy and GDPR compliance settings. These options help ensure your site respects user privacy.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render advanced section.
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced plugin settings.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render geo provider field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_geo_provider_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : 'ip-api';

		$providers = array(
			'ip-api'   => array(
				'name'  => 'ip-api.com',
				'limit' => __( '45 requests/minute, no API key', 'wb-ads-rotator-with-split-test' ),
			),
			'ipinfo'   => array(
				'name'  => 'ipinfo.io',
				'limit' => __( '50K requests/month, API key optional', 'wb-ads-rotator-with-split-test' ),
			),
			'ipapi-co' => array(
				'name'  => 'ipapi.co',
				'limit' => __( '1K requests/day, no API key', 'wb-ads-rotator-with-split-test' ),
			),
		);
		?>
		<fieldset>
			<?php foreach ( $providers as $key => $provider ) : ?>
				<label style="display: block; margin-bottom: 8px;">
					<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $value, $key ); ?> />
					<strong><?php echo esc_html( $provider['name'] ); ?></strong>
					<span class="description" style="margin-left: 5px;">(<?php echo esc_html( $provider['limit'] ); ?>)</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'If the primary provider fails, the system will automatically try the next provider.', 'wb-ads-rotator-with-split-test' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Features section intro.
	 */
	public function render_features_section() {
		echo '<p>' . esc_html__( 'Switch off anything this site does not use. Turning a feature off only hides its admin menu - no data is deleted.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render a module on/off checkbox.
	 *
	 * Modules default to enabled, so an absent stored value must read as on.
	 * That differs from render_checkbox_field(), which defaults to off.
	 *
	 * @since 2.9.2
	 * @param array $args Field arguments. Expects 'id', 'label', 'description'.
	 */
	public function render_module_field( $args ) {
		$slug    = $args['id'];
		$enabled = \WBAM\Core\Settings_Helper::is_module_enabled( $slug );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[modules][' . $slug . ']' ); ?>" value="1" <?php checked( $enabled ); ?> />
			<?php echo esc_html( $args['label'] ?? '' ); ?>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : false;
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="1" <?php checked( $value ); ?> />
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings    = $this->get_settings();
		$id          = $args['id'];
		$value       = isset( $settings[ $id ] ) ? $settings[ $id ] : '';
		$placeholder = $args['placeholder'] ?? '';
		?>
		<input type="text" id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="regular-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : 0;
		?>
		<input type="number" id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" class="small-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render select field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_select_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : '';
		$options  = $args['options'] ?? array();
		?>
		<select id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render post types field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_post_types_field( $args ) {
		$settings   = $this->get_settings();
		$id         = $args['id'];
		$value      = isset( $settings[ $id ] ) ? (array) $settings[ $id ] : array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<fieldset>
			<?php foreach ( $post_types as $post_type ) : ?>
				<?php
				if ( 'wbam-ad' === $post_type->name ) {
					continue;}
				?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . '][]' ); ?>" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $value, true ) ); ?> />
					<?php echo esc_html( $post_type->labels->name ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}
}
