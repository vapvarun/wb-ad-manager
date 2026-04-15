<?php
/**
 * Jetonomy Module.
 *
 * Integrates WB Ad Manager with the Jetonomy discussion/forum plugin
 * (https://store.wbcomdesigns.com/jetonomy/). Adds seven placement
 * positions that map to the template action hooks shipped with
 * Jetonomy v1.3.0+.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Modules\Jetonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WBAM\Modules\Placements\Placement_Engine;

/**
 * Jetonomy module bootstrap.
 */
class Jetonomy_Module {

	/**
	 * Initialize the module when Jetonomy is active.
	 */
	public function init() {
		if ( ! self::is_jetonomy_active() ) {
			return;
		}

		$engine = Placement_Engine::get_instance();
		$engine->register_placement( new Jetonomy_Placement() );
	}

	/**
	 * Detect whether Jetonomy is installed and loaded.
	 *
	 * @return bool
	 */
	public static function is_jetonomy_active() {
		return class_exists( '\\Jetonomy\\Jetonomy' ) || defined( 'JETONOMY_VERSION' );
	}
}

/**
 * Jetonomy placement handler.
 */
class Jetonomy_Placement implements \WBAM\Modules\Placements\Placement_Interface {

	/**
	 * Index of replies already processed in the current request, for the
	 * between-replies frequency logic.
	 *
	 * @var int
	 */
	private $reply_index = 0;

	/**
	 * Placement ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'jetonomy';
	}

	/**
	 * Placement label.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Jetonomy Discussions', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Placement description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display ads in Jetonomy forums, spaces, topic pages, and sidebars.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Placement group (for admin grouping).
	 *
	 * @return string
	 */
	public function get_group() {
		return 'jetonomy';
	}

	/**
	 * Whether the placement is usable on this site.
	 *
	 * @return bool
	 */
	public function is_available() {
		return Jetonomy_Module::is_jetonomy_active();
	}

	/**
	 * Whether to show this placement in the admin selector.
	 *
	 * @return bool
	 */
	public function show_in_selector() {
		return true;
	}

	/**
	 * Wire WordPress actions to render callbacks.
	 */
	public function register() {
		add_action( 'jetonomy_sidebar_before',      array( $this, 'render_sidebar_before' ) );
		add_action( 'jetonomy_sidebar_after_about', array( $this, 'render_sidebar_after_about' ) );
		add_action( 'jetonomy_sidebar_after',       array( $this, 'render_sidebar_after' ) );
		add_action( 'jetonomy_after_post_article',  array( $this, 'render_after_post_article' ) );
		add_action( 'jetonomy_before_replies',      array( $this, 'render_before_replies' ), 10, 2 );
		add_action( 'jetonomy_between_replies',     array( $this, 'render_between_replies' ), 10, 3 );
		add_action( 'jetonomy_after_replies',       array( $this, 'render_after_replies' ), 10, 2 );
	}

	/** Render handlers — one per exposed position. */
	public function render_sidebar_before()      { $this->render_position( 'sidebar_before' ); }
	public function render_sidebar_after_about() { $this->render_position( 'sidebar_after_about' ); }
	public function render_sidebar_after()       { $this->render_position( 'sidebar_after' ); }
	public function render_after_post_article()  { $this->render_position( 'after_post_article' ); }
	public function render_before_replies()      { $this->render_position( 'before_replies' ); }
	public function render_after_replies()       { $this->render_position( 'after_replies' ); }

	/**
	 * Between-replies handler applies frequency logic (every Nth reply).
	 *
	 * @param object $reply The reply just rendered.
	 * @param int    $index Zero-based index within the current batch.
	 * @param object $post  Current post object.
	 */
	public function render_between_replies( $reply, $index, $post ) {
		$this->reply_index = (int) $index + 1; // Convert to one-based for frequency math.

		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position = isset( $data['jetonomy_position'] ) ? $data['jetonomy_position'] : '';

			if ( 'between_replies' !== $ad_position ) {
				continue;
			}

			$every  = isset( $data['jetonomy_every'] ) ? max( 1, absint( $data['jetonomy_every'] ) ) : 5;
			$repeat = ! empty( $data['jetonomy_repeat'] );

			$show = $repeat ? ( 0 === $this->reply_index % $every ) : ( $this->reply_index === $every );
			if ( ! $show ) {
				continue;
			}

			$this->print_ad( $engine->render_ad( $ad_id ), 'between_replies' );
		}
	}

	/**
	 * Render any ads targeted at the given Jetonomy position.
	 *
	 * @param string $position Position key (e.g. sidebar_before).
	 */
	private function render_position( $position ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		foreach ( $ads as $ad_id ) {
			$data        = get_post_meta( $ad_id, '_wbam_ad_data', true );
			$ad_position = isset( $data['jetonomy_position'] ) ? $data['jetonomy_position'] : '';

			if ( $ad_position !== $position ) {
				continue;
			}

			$this->print_ad( $engine->render_ad( $ad_id ), $position );
		}
	}

	/**
	 * Output a rendered ad wrapped with a Jetonomy-namespaced container.
	 *
	 * @param string $html     Rendered ad HTML.
	 * @param string $position Position key for the CSS class.
	 */
	private function print_ad( $html, $position ) {
		if ( empty( $html ) ) {
			return;
		}

		printf(
			'<div class="wbam-jetonomy-ad wbam-jetonomy-%s">%s</div>',
			esc_attr( $position ),
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html comes from Placement_Engine which is already escaped/kses'd downstream.
		);
	}

	/**
	 * Render the position + frequency controls on the ad edit screen.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Ad data.
	 */
	public function render_options( $ad_id, $data ) {
		$position = isset( $data['jetonomy_position'] ) ? $data['jetonomy_position'] : 'sidebar_before';
		$every    = isset( $data['jetonomy_every'] ) ? absint( $data['jetonomy_every'] ) : 5;
		$repeat   = ! empty( $data['jetonomy_repeat'] );

		$positions = array(
			'sidebar_before'      => __( 'Sidebar — Top', 'wb-ads-rotator-with-split-test' ),
			'sidebar_after_about' => __( 'Sidebar — After About Card', 'wb-ads-rotator-with-split-test' ),
			'sidebar_after'       => __( 'Sidebar — Bottom', 'wb-ads-rotator-with-split-test' ),
			'after_post_article'  => __( 'After Topic Body', 'wb-ads-rotator-with-split-test' ),
			'before_replies'      => __( 'Before Replies', 'wb-ads-rotator-with-split-test' ),
			'between_replies'     => __( 'Between Replies', 'wb-ads-rotator-with-split-test' ),
			'after_replies'       => __( 'After Replies', 'wb-ads-rotator-with-split-test' ),
		);
		?>
		<div class="wbam-placement-extra">
			<label><?php esc_html_e( 'Jetonomy Position', 'wb-ads-rotator-with-split-test' ); ?></label>
			<select name="wbam_data[jetonomy_position]" class="wbam-jetonomy-position-select">
				<?php foreach ( $positions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $position, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wbam-placement-extra wbam-jetonomy-between-options" <?php echo 'between_replies' === $position ? '' : 'style="display:none;"'; ?>>
			<label><?php esc_html_e( 'Show after every', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" name="wbam_data[jetonomy_every]" value="<?php echo esc_attr( $every ); ?>" min="1" max="50" style="width:60px;" />
			<?php esc_html_e( 'replies', 'wb-ads-rotator-with-split-test' ); ?>
			<label style="margin-left:15px;">
				<input type="checkbox" name="wbam_data[jetonomy_repeat]" value="1" <?php checked( $repeat ); ?> />
				<?php esc_html_e( 'Repeat', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Sanitize and return the position + frequency settings for saving.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Raw posted data.
	 * @return array
	 */
	public function save_options( $ad_id, $data ) {
		$valid = array_keys(
			array(
				'sidebar_before'      => 1,
				'sidebar_after_about' => 1,
				'sidebar_after'       => 1,
				'after_post_article'  => 1,
				'before_replies'      => 1,
				'between_replies'     => 1,
				'after_replies'       => 1,
			)
		);

		$position = isset( $data['jetonomy_position'] ) ? sanitize_key( $data['jetonomy_position'] ) : 'sidebar_before';

		return array(
			'jetonomy_position' => in_array( $position, $valid, true ) ? $position : 'sidebar_before',
			'jetonomy_every'    => isset( $data['jetonomy_every'] ) ? absint( $data['jetonomy_every'] ) : 5,
			'jetonomy_repeat'   => ! empty( $data['jetonomy_repeat'] ),
		);
	}
}
