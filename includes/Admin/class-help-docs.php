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
				<?php if ( $this->is_pro_active ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=pro-features' ) ); ?>"
					class="nav-tab <?php echo 'pro-features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'PRO Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<?php endif; ?>
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
			<p><?php esc_html_e( 'A complete ad management and classifieds marketplace for WordPress.', 'wb-ads-rotator-with-split-test' ); ?></p>

			<div class="wbam-quick-start">
				<h3><?php esc_html_e( 'Quick Start Guide', 'wb-ads-rotator-with-split-test' ); ?></h3>

				<div class="wbam-step">
					<span class="wbam-step-number">1</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Create Your First Ad', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Go to WB Ad Manager > Add New. Choose Image, HTML, or Rich Text ad type. Set the placement and publish.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create Ad', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>

				<div class="wbam-step">
					<span class="wbam-step-number">2</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Set Up Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Ads display automatically in configured positions: header, footer, before/after content, sidebar widgets, and more.', 'wb-ads-rotator-with-split-test' ); ?></p>
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
					<span class="wbam-step-number">3</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Manage Links', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Create cloaked affiliate links for better tracking and cleaner URLs.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<div class="wbam-support-box">
				<h3><?php esc_html_e( 'Need Help?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'If you have questions or need support, please reach out to us.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="https://wbcomdesigns.com/contact/" target="_blank" class="button">
					<?php esc_html_e( 'Contact Support', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
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
					<h4><?php esc_html_e( 'Multiple Ad Types', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Image, HTML/JS, Rich Text, AdSense, and Video ads.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'map-pin', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Flexible Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Header, footer, before/after content, sidebar, and custom positions.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'link', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Link Cloaking', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Clean, branded URLs for affiliate links with click tracking.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'eye', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Display Rules', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Show or hide ads by page, category, post type, or user role.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'smartphone', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Device Targeting', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Target desktop, tablet, or mobile devices separately.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<?php echo wbam_icon( 'bar-chart-3', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<h4><?php esc_html_e( 'Click Tracking', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Track clicks on ads and links with basic analytics.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>
			</div>

			<?php if ( ! $this->is_pro_active ) : ?>
			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Want More Features?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Upgrade to PRO for classifieds marketplace, advertiser portal, wallet system, A/B testing, campaigns, and more!', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'View PRO Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render PRO Features tab (only shown when PRO is active).
	 */
	private function render_pro_features_tab() {
		if ( ! $this->is_pro_active ) {
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
					<li><?php esc_html_e( 'Partnership inquiries from link placement requests', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CSV bulk import for links and keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
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
				<p><?php esc_html_e( 'Image ads (with click URL), HTML/JavaScript code, Rich Text (WYSIWYG), Google AdSense, and Video embeds.', 'wb-ads-rotator-with-split-test' ); ?></p>
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
