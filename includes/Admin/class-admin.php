<?php
/**
 * Admin Class
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
use WBAM\Modules\Placements\Placement_Engine;

/**
 * Admin class.
 */
class Admin {

	use Singleton;

	/**
	 * Cache for table existence checks.
	 *
	 * @var array
	 */
	private static $table_cache = array();

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_wbam-ad_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_wbam-ad_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_disable_ad' ) );
		add_action( 'admin_init', array( $this, 'handle_row_toggle' ) );

		// Bulk-action pipeline on the Ads list table — enable/disable
		// selected ads in one click instead of opening each edit screen.
		add_filter( 'bulk_actions-edit-wbam-ad', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-wbam-ad', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_bulk_action_notice' ) );

		// Inline row-action link so a single "Disable" or "Enable"
		// click on a row does not require opening the edit screen.
		add_filter( 'post_row_actions', array( $this, 'add_row_action_toggle' ), 10, 2 );
	}

	/**
	 * Register bulk-action options on the Ads list table.
	 *
	 * @param array<string, string> $actions Existing bulk actions keyed by slug.
	 * @return array<string, string>
	 */
	public function register_bulk_actions( $actions ) {
		$actions['wbam_enable']  = __( 'Enable ads', 'wb-ads-rotator-with-split-test' );
		$actions['wbam_disable'] = __( 'Disable ads', 'wb-ads-rotator-with-split-test' );
		return $actions;
	}

	/**
	 * Execute one of our bulk actions. WP handles the nonce and capability
	 * check before this runs, so we only need to apply the meta change.
	 *
	 * @param string $redirect   URL to redirect to after handling.
	 * @param string $action     Action slug chosen from the dropdown.
	 * @param int[]  $post_ids   Selected post IDs.
	 * @return string Redirect URL with a counter query arg appended.
	 */
	public function handle_bulk_actions( $redirect, $action, $post_ids ) {
		if ( 'wbam_enable' !== $action && 'wbam_disable' !== $action ) {
			return $redirect;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect;
		}

		$value  = 'wbam_enable' === $action ? '1' : '0';
		$count  = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || 'wbam-ad' !== $post->post_type ) {
				continue;
			}
			update_post_meta( $post_id, '_wbam_enabled', $value );
			++$count;
		}

		return add_query_arg(
			array(
				'wbam_bulk_action' => $action,
				'wbam_bulk_count'  => $count,
			),
			$redirect
		);
	}

	/**
	 * Render the result notice after a bulk action. Hooks admin_notices
	 * instead of admin_init because the notice needs the query args the
	 * bulk handler put on the redirect URL.
	 */
	public function render_bulk_action_notice() {
		if ( empty( $_GET['wbam_bulk_action'] ) || empty( $_GET['wbam_bulk_count'] ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit-wbam-ad' !== $screen->id ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['wbam_bulk_action'] ) );
		$count  = absint( wp_unslash( $_GET['wbam_bulk_count'] ) );
		if ( 0 === $count ) {
			return;
		}

		$message = 'wbam_enable' === $action
			/* translators: %d: number of ads */
			? sprintf( _n( '%d ad enabled.', '%d ads enabled.', $count, 'wb-ads-rotator-with-split-test' ), $count )
			/* translators: %d: number of ads */
			: sprintf( _n( '%d ad disabled.', '%d ads disabled.', $count, 'wb-ads-rotator-with-split-test' ), $count );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Add an inline "Enable"/"Disable" toggle to each ad row so admins
	 * can flip a single ad without opening the edit screen.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post    Current row post.
	 * @return array<string, string>
	 */
	public function add_row_action_toggle( $actions, $post ) {
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$enabled  = (string) get_post_meta( $post->ID, '_wbam_enabled', true );
		$is_on    = '1' === $enabled;
		$next     = $is_on ? '0' : '1';
		$url      = wp_nonce_url(
			add_query_arg(
				array(
					'wbam_toggle_enabled' => $next,
					'post'                => $post->ID,
				),
				admin_url( 'edit.php?post_type=wbam-ad' )
			),
			'wbam_toggle_enabled_' . $post->ID
		);
		$label    = $is_on
			? __( 'Disable', 'wb-ads-rotator-with-split-test' )
			: __( 'Enable', 'wb-ads-rotator-with-split-test' );
		$class    = $is_on ? 'wbam-row-action-disable' : 'wbam-row-action-enable';

		$actions[ 'wbam_toggle' ] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_html( $label )
		);
		return $actions;
	}

	/**
	 * Handle disable ad from comparison view.
	 */
	public function handle_disable_ad() {
		if ( ! isset( $_GET['wbam_disable'] ) || '1' !== $_GET['wbam_disable'] ) {
			return;
		}

		if ( ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbam_disable_ad_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return;
		}

		// Disable the ad.
		update_post_meta( $post_id, '_wbam_enabled', '0' );

		// Add admin notice.
		add_action(
			'admin_notices',
			function () use ( $post ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Ad title */
						esc_html__( 'Ad "%s" has been disabled.', 'wb-ads-rotator-with-split-test' ),
						esc_html( $post->post_title )
					)
				);
			}
		);
	}

	/**
	 * Flip the enabled/disabled flag from the inline row action link.
	 * Back-end for the "Enable"/"Disable" entry added to each ad row.
	 */
	public function handle_row_toggle() {
		if ( ! isset( $_GET['wbam_toggle_enabled'], $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );
		if ( ! $post_id ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbam_toggle_enabled_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wbam-ad' !== $post->post_type ) {
			return;
		}

		$next = '1' === (string) $_GET['wbam_toggle_enabled'] ? '1' : '0';
		update_post_meta( $post_id, '_wbam_enabled', $next );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'        => 'wbam-ad',
					'wbam_bulk_action' => '1' === $next ? 'wbam_enable' : 'wbam_disable',
					'wbam_bulk_count'  => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wbam-admin',
			WBAM_URL . 'assets/css/admin' . $suffix . '.css',
			array(),
			WBAM_VERSION
		);

		wp_enqueue_script(
			'wbam-admin',
			WBAM_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery', 'media-editor' ),
			WBAM_VERSION,
			true
		);

		wp_localize_script(
			'wbam-admin',
			'wbamAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbam-admin' ),
				'i18n'    => array(
					'selectImage' => __( 'Select Image', 'wb-ads-rotator-with-split-test' ),
					'useImage'    => __( 'Use This Image', 'wb-ads-rotator-with-split-test' ),
				),
			)
		);

		// Expose the placement registry + format dimensions to the
		// ad-edit sizing section so the "Will render in:" live summary
		// can resolve matches client-side without an AJAX round-trip.
		$format_data = self::collect_format_js_data();
		if ( ! empty( $format_data ) ) {
			wp_localize_script( 'wbam-admin', 'wbamFormatData', $format_data );
		}

		// Code editor.
		if ( 'post' === $hook || 'post-new' === $hook ) {
			$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			if ( false !== $settings ) {
				wp_localize_script( 'wbam-admin', 'wbamCodeEditor', $settings );
			}
		}
	}

	/**
	 * Add metaboxes.
	 */
	public function add_metaboxes() {
		add_meta_box(
			'wbam-ad-settings',
			__( 'Ad Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_settings_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		// Preview metabox is only useful once the ad has been saved at
		// least once — before that there is no post meta to render from.
		global $post;
		if ( $post && $post->ID && 'auto-draft' !== $post->post_status ) {
			add_meta_box(
				'wbam-ad-preview',
				__( 'Preview', 'wb-ads-rotator-with-split-test' ),
				array( $this, 'render_preview_metabox' ),
				'wbam-ad',
				'normal',
				'high'
			);
		}

		add_meta_box(
			'wbam-ad-placements',
			__( 'Placements', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_placements_metabox' ),
			'wbam-ad',
			'normal',
			'high'
		);

		add_meta_box(
			'wbam-ad-status',
			__( 'Ad Status', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_status_metabox' ),
			'wbam-ad',
			'side',
			'high'
		);

		// Only show comparison metabox for existing ads with placements.
		global $post;
		if ( $post && $post->ID ) {
			$placements = get_post_meta( $post->ID, '_wbam_placements', true );
			if ( ! empty( $placements ) ) {
				add_meta_box(
					'wbam-ad-comparison',
					__( 'Ad Performance Comparison', 'wb-ads-rotator-with-split-test' ),
					array( $this, 'render_comparison_metabox' ),
					'wbam-ad',
					'normal',
					'default'
				);
			}
		}
	}

	/**
	 * Render settings metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_settings_metabox( $post ) {
		wp_nonce_field( 'wbam_save_ad', 'wbam_nonce' );

		$data    = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$data    = is_array( $data ) ? $data : array();
		$ad_type = isset( $data['type'] ) ? $data['type'] : 'image';

		$engine   = Placement_Engine::get_instance();
		$ad_types = $engine->get_ad_types();
		?>
		<div class="wbam-metabox wbam-adtype-tabs">
			<?php foreach ( $ad_types as $type ) : ?>
				<input type="radio"
						name="wbam_data[type]"
						value="<?php echo esc_attr( $type->get_id() ); ?>"
						id="wbam-adtype-<?php echo esc_attr( $type->get_id() ); ?>"
						class="wbam-adtype-radio"
						<?php checked( $ad_type, $type->get_id() ); ?> />
			<?php endforeach; ?>

			<div class="wbam-adtype-nav">
				<?php foreach ( $ad_types as $type ) : ?>
					<a href="#" class="wbam-adtype-tab<?php echo ( $ad_type === $type->get_id() ) ? ' wbam-adtype-tab-active' : ''; ?>" data-type="<?php echo esc_attr( $type->get_id() ); ?>">
						<span class="dashicons <?php echo esc_attr( $type->get_icon() ); ?>"></span>
						<?php echo esc_html( $type->get_name() ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $ad_types as $type ) : ?>
				<div class="wbam-adtype-content" data-type="<?php echo esc_attr( $type->get_id() ); ?>"<?php echo ( $ad_type === $type->get_id() ) ? ' style="display:block;"' : ''; ?>>
					<p class="wbam-adtype-desc"><?php echo esc_html( $type->get_description() ); ?></p>
					<?php $type->render_metabox( $post->ID, $data ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<script>
		jQuery(function($) {
			$('.wbam-adtype-tab').on('click', function(e) {
				e.preventDefault();
				var typeId = $(this).data('type');
				$('#wbam-adtype-' + typeId).prop('checked', true);
				$('.wbam-adtype-tab').removeClass('wbam-adtype-tab-active');
				$(this).addClass('wbam-adtype-tab-active');
				$('.wbam-adtype-content').hide();
				$('.wbam-adtype-content[data-type="' + typeId + '"]').show();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render placements metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_placements_metabox( $post ) {
		$placements = get_post_meta( $post->ID, '_wbam_placements', true );
		$placements = is_array( $placements ) ? $placements : array();

		$data             = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$after_paragraph  = isset( $data['after_paragraph'] ) ? absint( $data['after_paragraph'] ) : 2;
		$paragraph_repeat = isset( $data['paragraph_repeat'] ) ? $data['paragraph_repeat'] : false;
		$after_activity   = isset( $data['after_activity'] ) ? absint( $data['after_activity'] ) : 3;
		$activity_repeat  = isset( $data['activity_repeat'] ) ? $data['activity_repeat'] : false;
		$after_posts      = isset( $data['after_posts'] ) ? absint( $data['after_posts'] ) : 3;
		$posts_repeat     = isset( $data['posts_repeat'] ) ? $data['posts_repeat'] : false;

		$engine     = Placement_Engine::get_instance();
		$all_places = $engine->get_placements_grouped();
		?>
		<div class="wbam-metabox">
			<?php foreach ( $all_places as $group => $group_placements ) : ?>
				<div class="wbam-placement-group">
					<h4><?php echo esc_html( ucfirst( $group ) ); ?> <?php esc_html_e( 'Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<div class="wbam-placement-options">
						<?php foreach ( $group_placements as $placement ) : ?>
							<?php
							if ( ! $placement->is_available() || ! $placement->show_in_selector() ) {
								continue;}
							?>
							<label class="wbam-placement-option">
								<input type="checkbox" name="wbam_placements[]" value="<?php echo esc_attr( $placement->get_id() ); ?>" <?php checked( in_array( $placement->get_id(), $placements, true ) ); ?> />
								<span class="wbam-option-body">
									<span class="wbam-option-title"><?php echo esc_html( $placement->get_name() ); ?></span>
									<span class="wbam-option-desc"><?php echo esc_html( $placement->get_description() ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="wbam-extra-settings wbam-paragraph-settings" <?php echo ! in_array( 'after_paragraph', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Paragraph Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_paragraph"><?php esc_html_e( 'Insert after paragraph:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_paragraph" name="wbam_data[after_paragraph]" value="<?php echo esc_attr( $after_paragraph ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[paragraph_repeat]" value="1" <?php checked( $paragraph_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X paragraphs', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-activity-settings" <?php echo ! in_array( 'bp_activity', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Activity Stream Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_activity"><?php esc_html_e( 'Insert after activity:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_activity" name="wbam_data[after_activity]" value="<?php echo esc_attr( $after_activity ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[activity_repeat]" value="1" <?php checked( $activity_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X activities', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>

			<div class="wbam-extra-settings wbam-archive-settings" <?php echo ! in_array( 'archive', $placements, true ) ? 'style="display:none;"' : ''; ?>>
				<h4><?php esc_html_e( 'Archive Settings', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<div class="wbam-field">
					<label for="wbam_after_posts"><?php esc_html_e( 'Insert after post:', 'wb-ads-rotator-with-split-test' ); ?></label>
					<input type="number" id="wbam_after_posts" name="wbam_data[after_posts]" value="<?php echo esc_attr( $after_posts ); ?>" min="1" max="50" />
				</div>
				<div class="wbam-field">
					<label>
						<input type="checkbox" name="wbam_data[posts_repeat]" value="1" <?php checked( $posts_repeat ); ?> />
						<?php esc_html_e( 'Repeat after every X posts', 'wb-ads-rotator-with-split-test' ); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render status metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_status_metabox( $post ) {
		$enabled       = get_post_meta( $post->ID, '_wbam_enabled', true );
		$enabled       = '' === $enabled ? '1' : $enabled;
		$priority      = get_post_meta( $post->ID, '_wbam_priority', true );
		$priority      = '' === $priority ? 5 : absint( $priority );
		$session_limit = get_post_meta( $post->ID, '_wbam_session_limit', true );
		$session_limit = '' === $session_limit ? '' : absint( $session_limit );
		$is_responsive = get_post_meta( $post->ID, '_wbam_is_responsive', true );
		$ad_format     = get_post_meta( $post->ID, '_wbam_ad_format', true );
		$ad_width      = (int) get_post_meta( $post->ID, '_wbam_ad_width', true );
		$ad_height     = (int) get_post_meta( $post->ID, '_wbam_ad_height', true );
		$format_labels = \WBAM\Core\Ad_Formats::all();
		?>
		<div class="wbam-metabox">
			<div class="wbam-status-options">
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
					<span class="wbam-status-enabled"><?php esc_html_e( 'Enabled', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
				<label class="wbam-status-option">
					<input type="radio" name="wbam_enabled" value="0" <?php checked( $enabled, '0' ); ?> />
					<span class="wbam-status-disabled"><?php esc_html_e( 'Disabled', 'wb-ads-rotator-with-split-test' ); ?></span>
				</label>
			</div>

			<div class="wbam-priority-field">
				<label for="wbam_priority"><?php esc_html_e( 'Priority', 'wb-ads-rotator-with-split-test' ); ?></label><?php echo Field_Tooltips::tip_for( 'priority' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?>
				<input type="range" id="wbam_priority" name="wbam_priority" min="1" max="10" value="<?php echo esc_attr( $priority ); ?>" />
				<span class="wbam-priority-value"><?php echo esc_html( $priority ); ?></span>
				<p class="description"><?php esc_html_e( 'Higher priority = bigger share when multiple ads compete for the same slot. Default is 5.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p class="wbam-priority-share-hint" aria-live="polite">
					<?php /* Filled in by JS: 'In a 3-way tie with two default-priority ads, this ad wins ~X% of impressions.' */ ?>
				</p>
			</div>

			<div class="wbam-session-limit-field">
				<label for="wbam_session_limit"><?php esc_html_e( 'Session Limit', 'wb-ads-rotator-with-split-test' ); ?></label><?php echo Field_Tooltips::tip_for( 'session_limit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?>
				<input type="number" id="wbam_session_limit" name="wbam_session_limit" min="0" value="<?php echo esc_attr( $session_limit ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'wb-ads-rotator-with-split-test' ); ?>" />
				<p class="description"><?php esc_html_e( 'Max views per visitor session. Leave empty for unlimited.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-sizing-section">
				<div class="wbam-sizing-section__head">
					<h3 class="wbam-sizing-section__title"><?php esc_html_e( 'Sizing', 'wb-ads-rotator-with-split-test' ); ?><?php echo Field_Tooltips::tip_for( 'sizing_mode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?></h3>
					<span class="wbam-sizing-section__hint"><?php esc_html_e( 'Controls where this ad can render.', 'wb-ads-rotator-with-split-test' ); ?></span>
				</div>

				<div class="wbam-sizing-choice" role="radiogroup" aria-label="<?php esc_attr_e( 'Ad sizing mode', 'wb-ads-rotator-with-split-test' ); ?>">
					<label class="wbam-sizing-option <?php echo '1' === (string) $is_responsive ? 'is-active' : ''; ?>">
						<input type="radio" name="wbam_sizing_mode" value="responsive" <?php checked( '1', (string) $is_responsive ); ?> />
						<span class="wbam-sizing-option__title"><?php esc_html_e( 'Responsive', 'wb-ads-rotator-with-split-test' ); ?></span>
						<span class="wbam-sizing-option__desc"><?php esc_html_e( 'Fills any slot. Best for AdSense auto and fluid HTML.', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
					<label class="wbam-sizing-option <?php echo '1' !== (string) $is_responsive ? 'is-active' : ''; ?>">
						<input type="radio" name="wbam_sizing_mode" value="fixed" <?php checked( '1', (string) $is_responsive, false ) ? '' : checked( true, true ); ?> <?php echo '1' !== (string) $is_responsive ? 'checked' : ''; ?> />
						<span class="wbam-sizing-option__title"><?php esc_html_e( 'Fixed size', 'wb-ads-rotator-with-split-test' ); ?></span>
						<span class="wbam-sizing-option__desc"><?php esc_html_e( 'Known width and height. Matches only compatible slots.', 'wb-ads-rotator-with-split-test' ); ?></span>
					</label>
				</div>

				<!-- Hidden carrier so existing save pipeline (_wbam_is_responsive) keeps working. -->
				<input type="hidden" id="wbam_is_responsive" name="wbam_is_responsive" value="<?php echo '1' === (string) $is_responsive ? '1' : ''; ?>" />

				<div class="wbam-sizing-fixed-fields" <?php echo '1' === (string) $is_responsive ? 'hidden' : ''; ?>>
					<label for="wbam_ad_format" class="wbam-inline-label"><?php esc_html_e( 'Format', 'wb-ads-rotator-with-split-test' ); ?></label>
					<select id="wbam_ad_format" name="wbam_ad_format" class="wbam-sizing-fixed-fields__select">
						<option value=""><?php esc_html_e( 'Auto-detect from image', 'wb-ads-rotator-with-split-test' ); ?></option>
						<?php foreach ( $format_labels as $slug => $meta ) : ?>
							<?php
							if ( 'responsive' === $slug ) {
								continue; } // Responsive lives in the choice above.
							?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $ad_format, $slug ); ?>>
								<?php echo esc_html( $meta['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<div class="wbam-sizing-custom-dims" <?php echo 'custom' === $ad_format ? '' : 'hidden'; ?>>
						<span class="wbam-inline-label"><?php esc_html_e( 'Dimensions', 'wb-ads-rotator-with-split-test' ); ?></span><?php echo Field_Tooltips::tip_for( 'custom_dims' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped HTML. ?>
						<input type="number" name="wbam_ad_width" min="0" step="1" value="<?php echo esc_attr( $ad_width ? $ad_width : '' ); ?>" placeholder="W" class="small-text" />
						<span class="wbam-sizing-x">&times;</span>
						<input type="number" name="wbam_ad_height" min="0" step="1" value="<?php echo esc_attr( $ad_height ? $ad_height : '' ); ?>" placeholder="H" class="small-text" />
						<span class="wbam-sizing-units"><?php esc_html_e( 'px', 'wb-ads-rotator-with-split-test' ); ?></span>
					</div>
				</div>

				<div class="wbam-sizing-compat" aria-live="polite">
					<span class="wbam-sizing-compat__label"><?php esc_html_e( 'Will render in:', 'wb-ads-rotator-with-split-test' ); ?></span>
					<span class="wbam-sizing-compat__value"><?php esc_html_e( 'Calculating...', 'wb-ads-rotator-with-split-test' ); ?></span>
				</div>
			</div>

			<?php
			/**
			 * Action for adding additional metabox options.
			 *
			 * @since 1.0.0
			 * @param \WP_Post $post Post object.
			 */
			do_action( 'wbam_ad_metabox_options', $post );
			?>
		</div>
		<script>
		jQuery(function($) {
			// Priority slider live value + win-share transparency hint.
			//
			// Phase H.3: site owners and advertisers should see exactly
			// what raising the priority slider does. Frequency_Manager
			// builds a weighted pool where each ad contributes `priority`
			// copies, so the win share in a tie of N ads at priorities
			// p1..pN is p_i / sum(p). We illustrate the most common tie
			// scenario (3 ads, two at the default priority of 5).
			var priorityHintTpl = 
			<?php
				/* translators: %d: this ad's share of impressions (percent) in a 3-way priority tie */
				echo wp_json_encode( __( 'In a 3-way tie with two default-priority (5) ads, this ad would win about %d%% of impressions.', 'wb-ads-rotator-with-split-test' ) );
			?>
			;

			function updatePriorityHint( value ) {
				var p     = parseInt( value, 10 ) || 5;
				var share = Math.round( ( p / ( p + 5 + 5 ) ) * 100 );
				// Translation string uses printf-style %d / %% — un-escape
				// the literal percent so JS shows '33%' not '33%%'.
				$( '.wbam-priority-share-hint' ).text(
					priorityHintTpl.replace( '%d', share ).replace( /%%/g, '%' )
				);
			}

			$('#wbam_priority').on('input', function() {
				$(this).next('.wbam-priority-value').text(this.value);
				updatePriorityHint( this.value );
			});

			// Initial paint so the hint is visible on page load.
			updatePriorityHint( $( '#wbam_priority' ).val() );

			// Sizing section wiring. We use a two-choice radio group for the
			// sizing mode instead of a standalone checkbox so the mental
			// model is explicit: 'responsive' or 'fixed', with the fixed
			// controls only visible when fixed is selected.
			var $modeRadios = $('input[name="wbam_sizing_mode"]'),
				$hidden     = $('#wbam_is_responsive'),
				$fixed      = $('.wbam-sizing-fixed-fields'),
				$format     = $('#wbam_ad_format'),
				$customDims = $('.wbam-sizing-custom-dims'),
				$compat     = $('.wbam-sizing-compat__value'),
				$options    = $('.wbam-sizing-option');

			function currentMode() {
				return $modeRadios.filter(':checked').val() || 'responsive';
			}

			function syncMode() {
				var mode = currentMode();
				$hidden.val( mode === 'responsive' ? '1' : '' );
				$fixed.prop('hidden', mode === 'responsive');
				$options.each(function() {
					$(this).toggleClass('is-active', $(this).find('input[type="radio"]').is(':checked'));
				});
				updateCompat();
			}

			function syncCustomDims() {
				$customDims.prop('hidden', $format.val() !== 'custom');
				updateCompat();
			}

			// Compute the compatible placement names locally from the
			// data emitted in wbamFormatData (populated via wp_localize).
			// No AJAX round-trip — the match logic is pure and fast.
			function updateCompat() {
				if ( typeof window.wbamFormatData === 'undefined' ) {
					$compat.text('');
					return;
				}

				var mode   = currentMode(),
					format = mode === 'responsive' ? 'responsive' : ($format.val() || 'auto');

				if ( mode === 'fixed' && format === 'auto' ) {
					$compat.text( wbamFormatData.i18n.autoDetect );
					return;
				}

				if ( mode === 'fixed' && format === 'custom' ) {
					var w = parseInt($customDims.find('input[name="wbam_ad_width"]').val(), 10) || 0,
						h = parseInt($customDims.find('input[name="wbam_ad_height"]').val(), 10) || 0;
					if ( w <= 0 || h <= 0 ) {
						$compat.text( wbamFormatData.i18n.enterDims );
						return;
					}
					format = detectFormat(w, h);
					if ( format === 'custom' ) {
						$compat.text( wbamFormatData.i18n.noMatch );
						return;
					}
				}

				var matches = [];
				$.each( wbamFormatData.placements, function( slug, entry ) {
					if ( format === 'responsive' || (entry.accepted || []).indexOf( format ) !== -1 ) {
						matches.push( entry.name );
					}
				} );

				if ( matches.length === 0 ) {
					$compat.text( wbamFormatData.i18n.noMatch );
					return;
				}
				if ( matches.length === Object.keys(wbamFormatData.placements).length ) {
					$compat.text( wbamFormatData.i18n.every );
					return;
				}
				$compat.text( matches.join(', ') );
			}

			function detectFormat(w, h) {
				var found = 'custom';
				$.each( wbamFormatData.formats, function( slug, dims ) {
					if ( dims.w === w && dims.h === h ) {
						found = slug;
						return false;
					}
				} );
				return found;
			}

			$modeRadios.on('change', syncMode);
			$format.on('change', syncCustomDims);
			$customDims.on('input', 'input[type="number"]', updateCompat);

			syncMode();
			syncCustomDims();
		});
		</script>
		<?php
	}

	/**
	 * Render the Preview metabox.
	 *
	 * Shows an approximate render of the saved ad so the admin can verify
	 * their content without clicking through to a frontend page. Code ads
	 * are isolated in a sandboxed iframe so pasted scripts cannot touch the
	 * admin. AdSense shows a placeholder because the real AdSense script
	 * only runs on public pages.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render_preview_metabox( $post ) {
		$ad_data = get_post_meta( $post->ID, '_wbam_ad_data', true );
		$type    = is_array( $ad_data ) && ! empty( $ad_data['type'] ) ? (string) $ad_data['type'] : '';

		if ( '' === $type ) {
			echo '<p class="description">'
				. esc_html__( 'Save the ad first to see a preview.', 'wb-ads-rotator-with-split-test' )
				. '</p>';
			return;
		}

		echo '<p class="description" style="margin:0 0 10px;">'
			. esc_html__( 'Approximate render. Final appearance depends on the theme and placement wrapper. Save the ad to refresh this preview.', 'wb-ads-rotator-with-split-test' )
			. '</p>';

		echo '<div class="wbam-preview-stage" style="padding:20px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;min-height:60px;">';

		switch ( $type ) {
			case 'image':
				$this->render_preview_image( $ad_data );
				break;
			case 'rich_content':
				$this->render_preview_rich_content( $ad_data );
				break;
			case 'code':
				$this->render_preview_code( $ad_data );
				break;
			case 'adsense':
				$this->render_preview_adsense( $ad_data );
				break;
			case 'email_capture':
				$this->render_preview_email_capture( $ad_data, (int) $post->ID );
				break;
			default:
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: ad type slug */
						__( 'Preview not available for ad type: %s', 'wb-ads-rotator-with-split-test' ),
						$type
					)
				) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Image ad preview.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_image( $data ) {
		$url = isset( $data['image_url'] ) ? esc_url( $data['image_url'] ) : '';
		if ( '' === $url ) {
			echo '<p>' . esc_html__( 'No image selected.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}
		$alt  = isset( $data['alt_text'] ) ? $data['alt_text'] : '';
		$link = isset( $data['link_url'] ) ? $data['link_url'] : '';

		echo '<div style="text-align:center;">';
		if ( '' !== $link ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener nofollow"><img src="%s" alt="%s" style="max-width:100%%;height:auto;border:0;"></a>',
				esc_url( $link ),
				esc_attr( $url ),
				esc_attr( $alt )
			);
		} else {
			printf(
				'<img src="%s" alt="%s" style="max-width:100%%;height:auto;border:0;">',
				esc_attr( $url ),
				esc_attr( $alt )
			);
		}
		echo '</div>';
	}

	/**
	 * Rich-content ad preview.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_rich_content( $data ) {
		$content = isset( $data['content'] ) ? (string) $data['content'] : '';
		if ( '' === trim( $content ) ) {
			echo '<p>' . esc_html__( 'No content yet.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}
		echo '<div class="wbam-preview-rich">' . wp_kses_post( $content ) . '</div>';
	}

	/**
	 * Code ad preview rendered in a sandboxed iframe so pasted scripts
	 * cannot read admin cookies, modify the edit screen, or phone home
	 * on behalf of the logged-in admin.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_code( $data ) {
		$code = isset( $data['code'] ) ? (string) $data['code'] : '';
		if ( '' === trim( $code ) ) {
			echo '<p>' . esc_html__( 'No code pasted yet.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}
		// Build a minimal HTML document. Keep bg/color sensible so an
		// unstyled ad snippet doesn't render as white-on-white.
		$doc  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$doc .= '<style>body{margin:0;padding:12px;font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;background:#fff;}</style>';
		$doc .= '</head><body>' . $code . '</body></html>';
		printf(
			'<iframe sandbox="allow-scripts allow-same-origin" style="width:100%%;min-height:200px;border:0;background:#fff;" srcdoc="%s"></iframe>',
			esc_attr( $doc )
		);
	}

	/**
	 * AdSense preview. Real AdSense only loads on approved public pages,
	 * so show a labeled placeholder with the configured unit IDs.
	 *
	 * @param array<string,mixed> $data Ad data.
	 * @return void
	 */
	private function render_preview_adsense( $data ) {
		$pub = isset( $data['publisher_id'] ) ? (string) $data['publisher_id'] : '';
		if ( '' === $pub ) {
			$pub = (string) \WBAM\Core\Settings_Helper::get( 'adsense_publisher_id', '' );
		}
		$unit   = isset( $data['ad_unit_id'] ) ? (string) $data['ad_unit_id'] : '';
		$format = isset( $data['format'] ) ? (string) $data['format'] : 'auto';

		echo '<div style="padding:28px 20px;background:#fff;border:1px dashed #c3c4c7;border-radius:4px;text-align:center;">';
		echo '<div style="font-weight:600;color:#1d2327;margin-bottom:6px;">' . esc_html__( 'Google AdSense', 'wb-ads-rotator-with-split-test' ) . '</div>';
		echo '<div style="font-family:monospace;color:#50575e;font-size:13px;">';
		if ( '' !== $pub ) {
			echo esc_html( $pub );
		}
		if ( '' !== $unit ) {
			echo ' / ' . esc_html( $unit );
		}
		echo '</div>';
		echo '<div style="color:#8c8f94;font-size:12px;margin-top:10px;">' . esc_html(
			sprintf(
			/* translators: %s: AdSense format (auto, horizontal, etc.) */
				__( 'Format: %s. Real AdSense renders only on approved public pages.', 'wb-ads-rotator-with-split-test' ),
				$format
			)
		) . '</div>';
		echo '</div>';
	}

	/**
	 * Email Capture ad preview.
	 *
	 * @param array<string,mixed> $data    Ad data.
	 * @param int   $post_id Ad post ID (for CSS isolation hints).
	 * @return void
	 */
	private function render_preview_email_capture( $data, $post_id ) {
		$headline    = isset( $data['headline'] ) ? (string) $data['headline'] : __( 'Subscribe to our Newsletter', 'wb-ads-rotator-with-split-test' );
		$description = isset( $data['description'] ) ? (string) $data['description'] : '';
		$button      = isset( $data['button_text'] ) ? (string) $data['button_text'] : __( 'Subscribe', 'wb-ads-rotator-with-split-test' );
		$bg          = isset( $data['bg_color'] ) ? (string) $data['bg_color'] : '#ffffff';
		$text_color  = isset( $data['text_color'] ) ? (string) $data['text_color'] : '#1d2327';
		$btn_color   = isset( $data['button_color'] ) ? (string) $data['button_color'] : '#2271b1';
		$show_name   = ! empty( $data['show_name_field'] );
		$privacy     = isset( $data['privacy_text'] ) ? (string) $data['privacy_text'] : '';

		printf(
			'<div style="background:%s;color:%s;padding:22px;border-radius:6px;max-width:420px;margin:0 auto;">',
			esc_attr( $bg ),
			esc_attr( $text_color )
		);
		echo '<div style="font-size:18px;font-weight:700;margin-bottom:6px;">' . esc_html( $headline ) . '</div>';
		if ( '' !== $description ) {
			echo '<div style="font-size:13px;margin-bottom:14px;">' . esc_html( $description ) . '</div>';
		}
		if ( $show_name ) {
			echo '<input type="text" placeholder="' . esc_attr__( 'Your name', 'wb-ads-rotator-with-split-test' ) . '" disabled style="display:block;width:100%;padding:8px 10px;margin-bottom:8px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#1d2327;">';
		}
		echo '<input type="email" placeholder="' . esc_attr__( 'you@example.com', 'wb-ads-rotator-with-split-test' ) . '" disabled style="display:block;width:100%;padding:8px 10px;margin-bottom:8px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#1d2327;">';
		printf(
			'<button type="button" disabled style="background:%s;color:#fff;border:0;padding:9px 16px;border-radius:4px;font-weight:600;cursor:not-allowed;">%s</button>',
			esc_attr( $btn_color ),
			esc_html( $button )
		);
		if ( '' !== $privacy ) {
			echo '<div style="font-size:11px;margin-top:10px;opacity:.75;">' . esc_html( $privacy ) . '</div>';
		}
		echo '</div>';
		unset( $post_id );
	}

	/**
	 * Render comparison metabox.
	 *
	 * Shows performance comparison of all ads sharing the same placements.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_comparison_metabox( $post ) {
		global $wpdb;

		$current_placements = get_post_meta( $post->ID, '_wbam_placements', true );
		if ( empty( $current_placements ) || ! is_array( $current_placements ) ) {
			echo '<p>' . esc_html__( 'No placements assigned to this ad.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}

		// Find all ads that share at least one placement with current ad.
		$all_ads = get_posts(
			array(
				'post_type'      => 'wbam-ad',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'post__not_in'   => array( $post->ID ),
				'meta_query'     => array(
					array(
						'key'     => '_wbam_enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		// Filter to only ads sharing placements.
		$competing_ads = array();
		foreach ( $all_ads as $ad ) {
			$ad_placements = get_post_meta( $ad->ID, '_wbam_placements', true );
			if ( ! empty( $ad_placements ) && is_array( $ad_placements ) ) {
				$shared = array_intersect( $current_placements, $ad_placements );
				if ( ! empty( $shared ) ) {
					$competing_ads[] = $ad;
				}
			}
		}

		if ( empty( $competing_ads ) ) {
			echo '<p>' . esc_html__( 'No other enabled ads are using the same placements. Enable more ads to compare performance.', 'wb-ads-rotator-with-split-test' ) . '</p>';
			return;
		}

		// Add current ad to comparison.
		array_unshift( $competing_ads, $post );

		// Get stats for all ads.
		$table_name   = $wpdb->prefix . 'wbam_analytics';
		$table_exists = $this->table_exists( $table_name );

		$stats = array();
		foreach ( $competing_ads as $ad ) {
			$impressions = 0;
			$clicks      = 0;

			if ( $table_exists ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$impressions = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$ad->ID,
						'impression'
					)
				);
				$clicks      = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
						$ad->ID,
						'click'
					)
				);
				// phpcs:enable
			}

			$ctr = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;

			$stats[ $ad->ID ] = array(
				'id'          => $ad->ID,
				'title'       => $ad->post_title,
				'impressions' => $impressions,
				'clicks'      => $clicks,
				'ctr'         => $ctr,
				'is_current'  => $ad->ID === $post->ID,
			);
		}

		// Sort by CTR descending.
		usort(
			$stats,
			function ( $a, $b ) {
				return $b['ctr'] <=> $a['ctr'];
			}
		);

		// Find winner (highest CTR with at least 100 impressions).
		$winner_id = 0;
		foreach ( $stats as $stat ) {
			if ( $stat['impressions'] >= 100 ) {
				$winner_id = $stat['id'];
				break;
			}
		}

		// Find max CTR for bar scaling.
		$max_ctr = max( array_column( $stats, 'ctr' ) );
		$max_ctr = $max_ctr > 0 ? $max_ctr : 1;
		?>
		<style>
			.wbam-comparison-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
			.wbam-comparison-table th { text-align: left; padding: 10px; border-bottom: 2px solid #ddd; }
			.wbam-comparison-table td { padding: 10px; border-bottom: 1px solid #eee; }
			.wbam-comparison-table tr.wbam-current-ad { background: #f0f7ff; }
			.wbam-ctr-bar { background: #ddd; height: 20px; border-radius: 3px; overflow: hidden; min-width: 100px; }
			.wbam-ctr-fill { background: #2271b1; height: 100%; transition: width 0.3s; }
			.wbam-ctr-fill.winner { background: #00a32a; }
			.wbam-winner-badge { background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
			.wbam-current-badge { background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
			.wbam-disable-btn { color: #b32d2e !important; }
			.wbam-comparison-note { color: #666; font-style: italic; margin-top: 10px; }
		</style>

		<table class="wbam-comparison-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ad', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Impressions', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th><?php esc_html_e( 'Performance', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $stats as $stat ) : ?>
					<tr class="<?php echo $stat['is_current'] ? 'wbam-current-ad' : ''; ?>">
						<td>
							<?php if ( $stat['is_current'] ) : ?>
								<strong><?php echo esc_html( $stat['title'] ); ?></strong>
								<span class="wbam-current-badge"><?php esc_html_e( 'This Ad', 'wb-ads-rotator-with-split-test' ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $stat['id'] ) ); ?>">
									<?php echo esc_html( $stat['title'] ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $winner_id === $stat['id'] ) : ?>
								<span class="wbam-winner-badge"><?php esc_html_e( 'Winner', 'wb-ads-rotator-with-split-test' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $stat['impressions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $stat['clicks'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $stat['ctr'], 2 ) ); ?>%</td>
						<td>
							<div class="wbam-ctr-bar">
								<div class="wbam-ctr-fill <?php echo $winner_id === $stat['id'] ? 'winner' : ''; ?>"
									style="width: <?php echo esc_attr( ( $stat['ctr'] / $max_ctr ) * 100 ); ?>%"></div>
							</div>
						</td>
						<td>
							<?php if ( ! $stat['is_current'] && $winner_id !== $stat['id'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'post.php?post=' . $stat['id'] . '&action=edit&wbam_disable=1' ), 'wbam_disable_ad_' . $stat['id'] ) ); ?>"
									class="wbam-disable-btn"
									onclick="return confirm('<?php esc_attr_e( 'Disable this underperforming ad?', 'wb-ads-rotator-with-split-test' ); ?>');">
									<?php esc_html_e( 'Disable', 'wb-ads-rotator-with-split-test' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="wbam-comparison-note">
			<?php
			if ( 0 === $winner_id ) {
				esc_html_e( 'No winner yet. Ads need at least 100 impressions each for a meaningful comparison.', 'wb-ads-rotator-with-split-test' );
			} else {
				esc_html_e( 'Winner is the ad with highest CTR among those with 100+ impressions.', 'wb-ads-rotator-with-split-test' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wbam_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_nonce'] ) ), 'wbam_save_ad' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'wbam-ad' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save enabled status.
		$enabled = isset( $_POST['wbam_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['wbam_enabled'] ) ) : '1';
		update_post_meta( $post_id, '_wbam_enabled', $enabled );

		// Save priority.
		$priority = isset( $_POST['wbam_priority'] ) ? absint( wp_unslash( $_POST['wbam_priority'] ) ) : 5;
		$priority = max( 1, min( 10, $priority ) );
		update_post_meta( $post_id, '_wbam_priority', $priority );

		// Save session limit.
		$session_limit = isset( $_POST['wbam_session_limit'] ) && '' !== $_POST['wbam_session_limit']
			? absint( wp_unslash( $_POST['wbam_session_limit'] ) )
			: '';
		update_post_meta( $post_id, '_wbam_session_limit', $session_limit );

		// Save responsive flag. The sizing section emits a hidden
		// wbam_is_responsive input whose value is '1' when the
		// Responsive mode is selected and '' otherwise, so we check
		// the value rather than isset() (the field is always present).
		// A sibling wbam_sizing_mode radio is the authoritative source
		// of truth for UI rendering, but the hidden carrier keeps the
		// existing _wbam_is_responsive meta key stable for downstream
		// consumers (wrapper CSS, REST exposure, etc.).
		$mode_input    = isset( $_POST['wbam_sizing_mode'] ) ? sanitize_key( wp_unslash( $_POST['wbam_sizing_mode'] ) ) : '';
		$is_responsive = 'responsive' === $mode_input || ! empty( $_POST['wbam_is_responsive'] ) ? '1' : '0';
		update_post_meta( $post_id, '_wbam_is_responsive', $is_responsive );

		// Save ad format + dimensions. Resolution order:
		// 1. If Responsive ticked: format is 'responsive', dims cleared.
		// 2. Else if admin picked a named format (non-custom): store slug,
		// copy W/H from the taxonomy so downstream consumers have
		// dimensions without another lookup.
		// 3. Else if admin picked 'custom' with W/H: store as-is, detect
		// if dims match a named format and upgrade the slug for free.
		// 4. Else (Auto-detect option): call the detector against the
		// ad-type data; fall back to 'responsive' when indeterminate.
		$format_input = isset( $_POST['wbam_ad_format'] ) ? sanitize_text_field( wp_unslash( $_POST['wbam_ad_format'] ) ) : '';
		$width_input  = isset( $_POST['wbam_ad_width'] ) ? absint( wp_unslash( $_POST['wbam_ad_width'] ) ) : 0;
		$height_input = isset( $_POST['wbam_ad_height'] ) ? absint( wp_unslash( $_POST['wbam_ad_height'] ) ) : 0;

		$resolved = self::resolve_ad_format( $post_id, $is_responsive, $format_input, $width_input, $height_input );

		update_post_meta( $post_id, '_wbam_ad_format', $resolved['format'] );
		update_post_meta( $post_id, '_wbam_ad_width', $resolved['width'] );
		update_post_meta( $post_id, '_wbam_ad_height', $resolved['height'] );

		// Save placements.
		$placements = isset( $_POST['wbam_placements'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wbam_placements'] ) ) : array();
		update_post_meta( $post_id, '_wbam_placements', $placements );

		// Save ad data.
		if ( isset( $_POST['wbam_data'] ) ) {
			$raw_data = wp_unslash( $_POST['wbam_data'] ); // phpcs:ignore
			$ad_type  = isset( $raw_data['type'] ) ? sanitize_text_field( $raw_data['type'] ) : 'image';

			$engine  = Placement_Engine::get_instance();
			$handler = $engine->get_ad_type( $ad_type );

			$data = array( 'type' => $ad_type );

			if ( $handler ) {
				$type_data = $handler->save( $post_id, $raw_data );
				$data      = array_merge( $data, $type_data );
			}

			// Paragraph settings.
			$data['after_paragraph']  = isset( $raw_data['after_paragraph'] ) ? absint( $raw_data['after_paragraph'] ) : 2;
			$data['paragraph_repeat'] = isset( $raw_data['paragraph_repeat'] ) ? true : false;

			// Activity settings.
			$data['after_activity']  = isset( $raw_data['after_activity'] ) ? absint( $raw_data['after_activity'] ) : 3;
			$data['activity_repeat'] = isset( $raw_data['activity_repeat'] ) ? true : false;

			// Archive settings.
			$data['after_posts']  = isset( $raw_data['after_posts'] ) ? absint( $raw_data['after_posts'] ) : 3;
			$data['posts_repeat'] = isset( $raw_data['posts_repeat'] ) ? true : false;

			/**
			 * Filter ad data before saving.
			 *
			 * @since 2.3.0
			 * @param array $data     Ad data to save.
			 * @param int   $post_id  Ad post ID.
			 * @param array $raw_data Raw POST data.
			 */
			$data = apply_filters( 'wbam_ad_data_before_save', $data, $post_id, $raw_data );

			update_post_meta( $post_id, '_wbam_ad_data', $data );
		}

		/**
		 * Action fired after ad meta is saved.
		 *
		 * @since 1.0.0
		 * @param int $post_id Post ID.
		 */
		do_action( 'wbam_save_ad_meta', $post_id );
	}

	/**
	 * Build the JS-side payload consumed by the sizing section's
	 * "Will render in:" live compatibility summary.
	 *
	 * @since 2.8.1
	 * @return array
	 */
	private static function collect_format_js_data() {
		if ( ! class_exists( '\\WBAM\\Core\\Ad_Formats' ) ) {
			return array();
		}

		$formats_out = array();
		foreach ( \WBAM\Core\Ad_Formats::all() as $slug => $meta ) {
			$formats_out[ $slug ] = array(
				'w' => (int) $meta['width'],
				'h' => (int) $meta['height'],
			);
		}

		$placements_out = array();
		$registry       = apply_filters( 'wbam_get_placements', array() );
		if ( is_array( $registry ) ) {
			foreach ( $registry as $slug => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
					continue;
				}
				$placements_out[ $slug ] = array(
					'name'     => (string) $entry['name'],
					'accepted' => isset( $entry['accepted_formats'] ) ? (array) $entry['accepted_formats'] : array(),
				);
			}
		}

		return array(
			'formats'    => $formats_out,
			'placements' => $placements_out,
			'i18n'       => array(
				'autoDetect' => __( 'Auto-detected from your image on save.', 'wb-ads-rotator-with-split-test' ),
				'enterDims'  => __( 'Enter width x height to see matches.', 'wb-ads-rotator-with-split-test' ),
				'noMatch'    => __( 'No placements match this size yet.', 'wb-ads-rotator-with-split-test' ),
				'every'      => __( 'Every placement.', 'wb-ads-rotator-with-split-test' ),
			),
		);
	}

	/**
	 * Resolve the final format slug + dimensions for an ad.
	 *
	 * Pure function; safe to unit-test in isolation. See the comment
	 * in save_metaboxes() for the resolution order.
	 *
	 * @since 2.8.1
	 * @param int    $post_id       Ad post ID (read ad-type data for auto-detect).
	 * @param string $is_responsive '1' if the Responsive flag is ticked.
	 * @param string $format_input  Admin-selected format slug ('' = auto-detect).
	 * @param int    $width_input   Width from the Custom W x H inputs.
	 * @param int    $height_input  Height from the Custom W x H inputs.
	 * @return array{format:string, width:int, height:int}
	 */
	private static function resolve_ad_format( $post_id, $is_responsive, $format_input, $width_input, $height_input ) {
		// Rule 1: Responsive flag wins.
		if ( '1' === (string) $is_responsive ) {
			return array(
				'format' => \WBAM\Core\Ad_Formats::RESPONSIVE,
				'width'  => 0,
				'height' => 0,
			);
		}

		$all = \WBAM\Core\Ad_Formats::all();

		// Rule 2: Named format picked.
		if ( '' !== $format_input && isset( $all[ $format_input ] ) && 'custom' !== $format_input ) {
			$meta = $all[ $format_input ];
			return array(
				'format' => $format_input,
				'width'  => (int) $meta['width'],
				'height' => (int) $meta['height'],
			);
		}

		// Rule 3: Custom W x H.
		if ( 'custom' === $format_input && $width_input > 0 && $height_input > 0 ) {
			$detected = \WBAM\Core\Ad_Formats::detect_by_dimensions( $width_input, $height_input );
			return array(
				'format' => $detected, // may auto-upgrade to a named slug when dims match.
				'width'  => $width_input,
				'height' => $height_input,
			);
		}

		// Rule 4: Auto-detect from ad-type data.
		$dims = self::detect_ad_dimensions( $post_id );
		if ( $dims['width'] > 0 && $dims['height'] > 0 ) {
			$detected = \WBAM\Core\Ad_Formats::detect_by_dimensions( $dims['width'], $dims['height'] );
			return array(
				'format' => $detected,
				'width'  => $dims['width'],
				'height' => $dims['height'],
			);
		}

		// Fallback: responsive. Safe permissive default — the ad will
		// render in every placement until someone corrects the format.
		return array(
			'format' => \WBAM\Core\Ad_Formats::RESPONSIVE,
			'width'  => 0,
			'height' => 0,
		);
	}

	/**
	 * Best-effort dimension detection for an ad, read from its type data.
	 *
	 * Image ads: if the image_url points to a local attachment, read the
	 * attachment metadata. External URLs return zeros (we don't fetch
	 * remote images during a save — that's a blocking network call and
	 * a privacy surface).
	 *
	 * Code / AdSense / Rich / Email Capture: no deterministic size, so
	 * we return zeros and let the caller fall back to responsive.
	 *
	 * @since 2.8.1
	 * @param int $post_id Ad post ID.
	 * @return array{width:int, height:int}
	 */
	private static function detect_ad_dimensions( $post_id ) {
		$data = get_post_meta( $post_id, '_wbam_ad_data', true );
		$type = is_array( $data ) && ! empty( $data['type'] ) ? (string) $data['type'] : '';

		if ( 'image' !== $type ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		$image_url = isset( $data['image_url'] ) ? (string) $data['image_url'] : '';
		if ( '' === $image_url ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		$attachment_id = attachment_url_to_postid( $image_url );
		if ( $attachment_id <= 0 ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $src ) ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		return array(
			'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
			'height' => isset( $src[2] ) ? (int) $src[2] : 0,
		);
	}

	/**
	 * Add columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'title' === $key ) {
				$new['ad_type']     = __( 'Type', 'wb-ads-rotator-with-split-test' );
				$new['placements']  = __( 'Placements', 'wb-ads-rotator-with-split-test' );
				$new['impressions'] = __( 'Impressions', 'wb-ads-rotator-with-split-test' );
				$new['clicks']      = __( 'Clicks', 'wb-ads-rotator-with-split-test' );
				$new['status']      = __( 'Status', 'wb-ads-rotator-with-split-test' );
			}
		}
		return $new;
	}

	/**
	 * Render column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'ad_type':
				$data    = get_post_meta( $post_id, '_wbam_ad_data', true );
				$type_id = isset( $data['type'] ) ? $data['type'] : '';
				$engine  = Placement_Engine::get_instance();
				$type    = $engine->get_ad_type( $type_id );
				if ( $type ) {
					echo '<span class="dashicons ' . esc_attr( $type->get_icon() ) . '"></span> ' . esc_html( $type->get_name() );
				}
				break;

			case 'placements':
				$placements = get_post_meta( $post_id, '_wbam_placements', true );
				echo ! empty( $placements ) ? esc_html( implode( ', ', $placements ) ) : '—';
				break;

			case 'impressions':
				$cache_key = 'wbam_impressions_' . $post_id;
				$count     = wp_cache_get( $cache_key, 'wbam' );
				if ( false === $count ) {
					global $wpdb;
					$table_name = $wpdb->prefix . 'wbam_analytics';
					// Check if table exists (cached to avoid repeated queries).
					if ( $this->table_exists( $table_name ) ) {
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$count = $wpdb->get_var(
							$wpdb->prepare(
								'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
								$post_id,
								'impression'
							)
						);
						// phpcs:enable
					} else {
						$count = 0;
					}
					wp_cache_set( $cache_key, $count, 'wbam', HOUR_IN_SECONDS );
				}
				echo '<strong>' . esc_html( number_format_i18n( absint( $count ) ) ) . '</strong>';
				break;

			case 'clicks':
				$cache_key = 'wbam_clicks_' . $post_id;
				$count     = wp_cache_get( $cache_key, 'wbam' );
				if ( false === $count ) {
					global $wpdb;
					$table_name = $wpdb->prefix . 'wbam_analytics';
					// Check if table exists (cached to avoid repeated queries).
					if ( $this->table_exists( $table_name ) ) {
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$count = $wpdb->get_var(
							$wpdb->prepare(
								'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wbam_analytics WHERE ad_id = %d AND event_type = %s',
								$post_id,
								'click'
							)
						);
						// phpcs:enable
					} else {
						$count = 0;
					}
					wp_cache_set( $cache_key, $count, 'wbam', HOUR_IN_SECONDS );
				}
				echo '<strong>' . esc_html( number_format_i18n( absint( $count ) ) ) . '</strong>';
				break;

			case 'status':
				$enabled = get_post_meta( $post_id, '_wbam_enabled', true );
				$class   = '1' === $enabled ? 'wbam-enabled' : 'wbam-disabled';
				$text    = '1' === $enabled ? __( 'Enabled', 'wb-ads-rotator-with-split-test' ) : __( 'Disabled', 'wb-ads-rotator-with-split-test' );
				echo '<span class="wbam-status-badge ' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
				break;
		}
	}

	/**
	 * Check if a database table exists (with static caching).
	 *
	 * Caches the result to avoid repeated SHOW TABLES queries during
	 * the same request. Tables are created during plugin activation,
	 * so they should always exist at runtime.
	 *
	 * @since 2.3.1
	 *
	 * @param string $table_name Full table name including prefix.
	 * @return bool True if table exists, false otherwise.
	 */
	private function table_exists( $table_name ) {
		// Return cached result if available.
		if ( isset( self::$table_cache[ $table_name ] ) ) {
			return self::$table_cache[ $table_name ];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		// Cache the result for subsequent calls.
		self::$table_cache[ $table_name ] = ! empty( $exists );

		return self::$table_cache[ $table_name ];
	}
}
