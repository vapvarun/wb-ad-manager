<?php
/**
 * Help & Documentation Admin Page
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Help_Docs class.
 */
class Help_Docs {

	/**
	 * Instance.
	 *
	 * @var Help_Docs
	 */
	private static $instance = null;

	/**
	 * Is PRO active.
	 *
	 * @var bool
	 */
	private $is_pro_active = false;

	/**
	 * Get instance.
	 *
	 * @return Help_Docs
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->is_pro_active = defined( 'WBAM_PRO_VERSION' );
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Help & Docs', 'wb-ads-rotator-with-split-test' ),
			__( 'Help & Docs', 'wb-ads-rotator-with-split-test' ),
			'manage_options',
			'wbam-help',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue styles.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_styles( $hook ) {
		if ( 'wbam-ad_page_wbam-help' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wbam-help-docs',
			WBAM_URL . 'assets/css/help-docs.css',
			array(),
			WBAM_VERSION
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'getting-started';
		?>
		<div class="wrap wbam-help-wrap">
			<h1><?php esc_html_e( 'Help & Documentation', 'wb-ads-rotator-with-split-test' ); ?></h1>

			<nav class="nav-tab-wrapper wbam-nav-tabs">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=getting-started' ) ); ?>"
					class="nav-tab <?php echo 'getting-started' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Getting Started', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=features' ) ); ?>"
					class="nav-tab <?php echo 'features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=pro-features' ) ); ?>"
					class="nav-tab <?php echo 'pro-features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo $this->is_pro_active
						? esc_html__( 'PRO Features', 'wb-ads-rotator-with-split-test' )
						: esc_html__( 'What\'s in PRO', 'wb-ads-rotator-with-split-test' );
					?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=faq' ) ); ?>"
					class="nav-tab <?php echo 'faq' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'FAQ', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</nav>

			<div class="wbam-help-content">
				<?php
				switch ( $active_tab ) {
					case 'features':
						$this->render_features_tab();
						break;
					case 'pro-features':
						$this->render_pro_features_tab();
						break;
					case 'faq':
						$this->render_faq_tab();
						break;
					default:
						$this->render_getting_started_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Getting Started tab.
	 */
	private function render_getting_started_tab() {
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'Welcome to WB Ad Manager', 'wb-ads-rotator-with-split-test' ); ?></h2>
			<?php if ( $this->is_pro_active ) : ?>
				<p><?php esc_html_e( 'Ad management and classifieds marketplace for WordPress — with advertisers, wallet, campaigns, and analytics.', 'wb-ads-rotator-with-split-test' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Complete ad management for WordPress — five ad types, sixteen placements, targeting, A/B comparison, and click tracking.', 'wb-ads-rotator-with-split-test' ); ?></p>
			<?php endif; ?>

			<div class="wbam-quick-start">
				<h3><?php esc_html_e( 'Quick Start Guide', 'wb-ads-rotator-with-split-test' ); ?></h3>

				<?php if ( ! $this->is_pro_active ) : ?>
				<div class="wbam-step">
					<span class="wbam-step-number">1</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Run the Setup Wizard', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'The wizard seeds three sample ads across the header, sidebar, and in-content placements so you can see the plugin in action in under a minute. You can remove the samples any time with one click.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'index.php?page=wbam-setup' ) ); ?>" class="button">
							<?php esc_html_e( 'Open Setup Wizard', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>
				<?php endif; ?>

				<div class="wbam-step">
					<span class="wbam-step-number"><?php echo $this->is_pro_active ? '1' : '2'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?></span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Create Your First Ad', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Go to WB Ad Manager > Add New. Choose Image, Rich Content, HTML/JS Code, AdSense, or Email Capture. Assign placements and publish.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create Ad', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>

				<div class="wbam-step">
					<span class="wbam-step-number"><?php echo $this->is_pro_active ? '2' : '3'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal. ?></span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Set Up Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Ads display automatically in the placements you pick: header, footer, before/after content, paragraph, sidebar widgets, popup, sticky bar, archive, comments, BuddyPress, bbPress, and Jetonomy.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>
				</div>

				<?php if ( $this->is_pro_active ) : ?>
				<div class="wbam-step">
					<span class="wbam-step-number">3</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Import Demo Data', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'See all features in action with sample ads, classifieds, advertisers, and analytics. Import from the Tools page and remove when ready.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-tools' ) ); ?>" class="button">
							<?php esc_html_e( 'Go to Tools', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>

				<div class="wbam-step">
					<span class="wbam-step-number">4</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Configure Modules', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Enable or disable features like Classifieds, Campaigns, Wallet, A/B Testing, and more from Pro Settings > Modules.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-pro-settings&tab=modules' ) ); ?>" class="button">
							<?php esc_html_e( 'Manage Modules', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>
				<?php else : ?>
				<div class="wbam-step">
					<span class="wbam-step-number">4</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Manage Links & Partnerships', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Create cloaked affiliate links with click tracking, group them into categories, and accept inbound partnership inquiries with the [wbam_partnership_inquiry] shortcode.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links' ) ); ?>" class="button">
							<?php esc_html_e( 'Open Links', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<div class="wbam-support-box">
				<h3><?php esc_html_e( 'Need Help?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Free users get community support on the WordPress.org forum. Pro customers can open a priority ticket with Wbcom Designs.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p>
					<a href="https://wordpress.org/support/plugin/wb-ads-rotator-with-split-test/" target="_blank" rel="noopener" class="button">
						<?php esc_html_e( 'WordPress.org Support Forum', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
					<a href="https://wbcomdesigns.com/contact/" target="_blank" rel="noopener" class="button">
						<?php esc_html_e( 'Contact Wbcom Support', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Features tab.
	 */
	private function render_features_tab() {
		$version_label = $this->is_pro_active ? __( 'PRO', 'wb-ads-rotator-with-split-test' ) : __( 'FREE', 'wb-ads-rotator-with-split-test' );
		?>
		<div class="wbam-help-section">
			<?php /* translators: %s: version label (FREE or PRO) */ ?>
			<h2><?php printf( esc_html__( 'Features (%s Version)', 'wb-ads-rotator-with-split-test' ), esc_html( $version_label ) ); ?></h2>

			<div class="wbam-features-grid">
				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'image', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( '5 Ad Types', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Image, Rich Content, HTML/JS Code, Google AdSense, and Email Capture.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'map-pin', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( '16+ Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Header, footer, paragraph, archive, sidebar widgets, popup (delay / scroll / exit intent), sticky bar (4 positions), comments, shortcode.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'users', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Community Integrations', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'BuddyPress (activity + 6 directory positions), bbPress (7 positions + between replies), Jetonomy (7 positions).', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'eye', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Display Rules & Scheduling', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Per-page, category, post type, and user role. Start / end dates, day of week, and time of day.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'smartphone', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Device & Geo Targeting', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Desktop / tablet / mobile targeting plus country-level geo targeting via ip-api.com, ipinfo.io, or ipapi.co.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'gauge', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Rotation & Frequency', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Weighted priority rotation (1–10 slider), global max-ads-per-page, per-ad session impression cap, lazy loading.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'bar-chart-3', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'A/B Comparison', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Side-by-side CTR comparison metabox with "winner" badge across ads sharing a placement.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'link', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Link Management', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Cloaked affiliate links with categories, click tracking, redirect types, and nofollow / sponsored rel attributes.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'handshake', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Link Partnerships', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Accept paid link / exchange / sponsored post inquiries via [wbam_partnership_inquiry] with accept/reject workflow and auto emails.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'mail', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Email Capture', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Inline newsletter form as an ad type — customizable colours, optional name field, hooks for Mailchimp / ConvertKit / webhooks.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'shield-check', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Privacy & GDPR', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'IP anonymization, consent-gated AdSense loading, no raw IPs stored, opt-in delete-on-uninstall.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'code', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Developer API', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'REST API (21 routes), 100+ action/filter hooks, custom ad-type and placement extension points, full translation support.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>
			</div>

			<?php if ( ! $this->is_pro_active ) : ?>
			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Unlock PRO', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Turn your site into an ad marketplace. Pro adds an advertiser portal, wallet & payments, classifieds, campaigns with budgets, advanced analytics, and more. Keep all the Free features — add revenue on top.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=pro-features' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'See PRO Features', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button">
						<?php esc_html_e( 'Full Free vs PRO Comparison', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render PRO Features tab (teaser when Pro isn't active, full guide when it is).
	 */
	private function render_pro_features_tab() {
		if ( ! $this->is_pro_active ) {
			$this->render_pro_teaser();
			return;
		}
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'PRO Features Guide', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Classifieds Marketplace', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'A full-featured classifieds system for your site.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Users post and browse classified listings with images', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Category and location filters with sidebar search', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Listing upgrades: Featured, Highlighted, Urgent, Bump', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Inquiry system for buyer-seller communication', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Reviews and ratings for sellers', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Custom fields builder for category-specific data', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advertiser Portal', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'A self-service dashboard for advertisers with 14 tabs.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Overview, My Ads, Campaigns, Classifieds, Inquiries', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Favorites, Following, Messages, Link Partnerships', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Wallet (credit balance and transaction history)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Membership plans, Analytics, Share of Voice, Profile', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Credits & Wallet System', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Built-in credit system powered by Wbcom Credits SDK.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Advertisers purchase credits to pay for ads and listings', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Supports WooCommerce, Stripe, PayPal, and manual payments', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Hold > Deduct > Refund lifecycle for safe billing', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Full transaction ledger with audit trail', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Campaigns & Packages', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Create ad packages with pricing, duration, and impression limits', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Flat, CPM, and CPC pricing models', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Campaign scheduling with start/end dates and budget caps', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Budget-aware pacing and per-advertiser session caps', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Analytics', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Impressions, clicks, and CTR tracking with daily aggregation', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Geographic and device breakdown', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Revenue dashboard and billing-proof ledger', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Slot inventory view (AdSense-style capacity overview)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Share of Voice analysis per advertiser', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'A/B Testing & Rotation', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Test ad variations with traffic splitting', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Fair rotation engine with equal-share distribution', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Frequency caps per advertiser and campaign', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Membership Plans', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Create subscription plans with listing limits and billing cycles', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Monthly, quarterly, and yearly billing', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Automatic renewal and expiration notifications', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Link Management (PRO)', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Auto-linking keywords in your content', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Link health checker (broken links, redirects)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Link Scanner — find monetization opportunities in existing content', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CSV bulk import for links and keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the "What's in PRO" teaser (shown on free installs).
	 *
	 * Mirrors the same categories as the Upgrade page so free users get
	 * a clear overview without clicking away.
	 */
	private function render_pro_teaser() {
		?>
		<div class="wbam-help-section wbam-pro-teaser">
			<h2><?php esc_html_e( 'What\'s in WB Ad Manager PRO', 'wb-ads-rotator-with-split-test' ); ?></h2>
			<p class="wbam-pro-teaser-intro">
				<?php esc_html_e( 'PRO keeps everything you have in the Free plugin and adds a full monetization layer — advertiser portal, wallet, campaigns, classifieds, and revenue analytics. Here\'s what you get when you upgrade.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advertiser Portal', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Let advertisers sign up, submit, and manage their own ads — you review & approve.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Advertiser registration and dedicated dashboard (14 tabs: Overview, My Ads, Campaigns, Classifieds, Inquiries, Favorites, Following, Messages, Link Partnerships, Wallet, Membership, Analytics, Share of Voice, Profile)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Self-service ad submission with admin review queue', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Per-advertiser caps and share-of-voice reporting', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Wallet, Credits & Payments', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Prepaid credit wallet per advertiser (hold → deduct → refund lifecycle)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'WooCommerce, Stripe, PayPal, and manual top-up integrations', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Full transaction ledger with audit trail and CSV export', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CPM, CPC, and flat-rate billing models', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Campaigns & Packages', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Ad packages with price, duration, and impression limits', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Campaign scheduling with start / end dates and budget caps', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Budget-aware pacing and per-advertiser session caps', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Membership plans (monthly / quarterly / yearly) with listing limits and auto-renewal', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Classifieds Marketplace', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Full classified listings system with image galleries', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Category + location taxonomies with sidebar search', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Paid upgrades: Featured, Highlighted, Urgent, Bump to top', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Multiple price types — fixed, negotiable, free', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Buyer inquiry system, favorites / saved listings, seller profiles with reviews and ratings', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Custom fields builder for category-specific data', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Analytics & A/B Testing', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Daily impression & click aggregation with time-series reports', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CTR and revenue reports, geo + device breakdowns, CSV export', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'A/B testing with statistical significance and traffic splitting', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Slot inventory view — AdSense-style capacity overview', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Share of Voice analysis per advertiser', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Link Management', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Keyword auto-linking — automatically link mentions of your keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Link Scanner — find monetization opportunities in existing posts', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Broken-link detection and redirect management', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CSV bulk import for links and keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Advanced link analytics (referrer, device, country)', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Community & Developer Extras', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Enhanced BuddyPress integration — seller profiles in member directory, activity stream for listings, following/favorites system', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Admin audit logs of every ad / credit / campaign action', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Ad review queue with approval workflow', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Priority support from Wbcom Designs', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Ready to turn your site into a revenue engine?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'See the full side-by-side comparison and pick your plan.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Full Free vs PRO Comparison', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
					<a href="https://wbcomdesigns.com/downloads/wb-ad-manager-pro/" target="_blank" rel="noopener" class="button button-hero">
						<?php esc_html_e( 'View Pricing on wbcomdesigns.com', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render FAQ tab.
	 */
	private function render_faq_tab() {
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'Frequently Asked Questions', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<h3><?php esc_html_e( 'Ads', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I display an ad?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Create an ad under WB Ad Manager > Add New, select a placement (header, footer, sidebar, before/after content), and publish. The ad appears automatically in that position on your site.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Can I show different ads on different pages?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Yes. Use the Display Rules metabox when editing an ad. You can target specific pages, categories, post types, or user roles.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'What ad types are supported?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Five ad types: Image (with click URL and alt text), Rich Content (HTML-editor), HTML/JavaScript Code, Google AdSense, and Email Capture (inline newsletter form).', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How does the Email Capture ad type work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Email Capture renders an inline subscribe form anywhere you assign it as a placement. You control the headline, description, button text, colours, and success message. Submissions fire the wbam_email_captured action so you can forward them to Mailchimp, ConvertKit, or any webhook — there\'s no external service tie-in.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Can I accept partnership / paid link inquiries from my site?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Yes. Drop [wbam_partnership_inquiry] on any page. Visitors fill out a structured form (partnership type, budget, target page, anchor text). Inquiries appear under WB Ad Manager → Link Partnerships with accept / reject workflow and automatic email notifications.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<h3><?php esc_html_e( 'Links', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do cloaked links work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Cloaked links redirect visitors through your domain (e.g., yoursite.com/go/amazon) to the destination URL. This makes links cleaner and enables click tracking.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'My cloaked links show a 404 page. How do I fix this?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Go to Settings > Permalinks and click "Save Changes" to flush the rewrite rules. This resolves the issue.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<?php if ( $this->is_pro_active ) : ?>
			<h3><?php esc_html_e( 'Classifieds', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do users post classifieds?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Users visit the Advertiser Dashboard page and use the Classifieds tab to submit listings. The multi-step wizard guides them through title, description, images, category, price, and contact info. Listings can require admin approval before going live.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How does the credit/wallet system work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Advertisers purchase credits which are used to pay for ad submissions, classified listings, and upgrades. Credits are held when a listing is submitted and deducted when approved (or refunded if rejected). Configure payment methods in Pro Settings > Credits.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Where is the Advertiser Dashboard?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p>
					<?php esc_html_e( 'The dashboard is created automatically on plugin activation. You can find or reassign it in', 'wb-ads-rotator-with-split-test' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-pro-settings&tab=pages' ) ); ?>"><?php esc_html_e( 'Pro Settings > Pages', 'wb-ads-rotator-with-split-test' ); ?></a>.
				</p>
			</div>

			<h3><?php esc_html_e( 'Demo Data', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I import demo data?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p>
					<?php esc_html_e( 'Go to', 'wb-ads-rotator-with-split-test' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-tools' ) ); ?>"><?php esc_html_e( 'WB Ad Manager > Tools', 'wb-ads-rotator-with-split-test' ); ?></a>
					<?php esc_html_e( 'and click "Import Demo Data". This creates sample ads, classifieds, advertisers, campaigns, and 30 days of analytics.', 'wb-ads-rotator-with-split-test' ); ?>
				</p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I remove demo data?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'After importing, the Tools page shows a "Remove All Demo Data" button with an itemized list of what will be deleted. Your real content is never touched — every item is verified against the demo flag before removal.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
			<?php endif; ?>

			<div class="wbam-support-box" style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Still have questions?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<a href="https://wbcomdesigns.com/contact/" target="_blank" class="button">
					<?php esc_html_e( 'Contact Support', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
