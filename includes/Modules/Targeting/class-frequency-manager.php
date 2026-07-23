<?php
/**
 * Frequency Manager
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Targeting;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;
use WBAM\Admin\Settings;

/**
 * Frequency Manager class.
 */
class Frequency_Manager {

	use Singleton;

	/**
	 * Cookie name for session tracking.
	 */
	const COOKIE_NAME = 'wbam_ad_views';

	/**
	 * Cookie expiration (1 day).
	 */
	const COOKIE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Total lifetime impressions this ad may be delivered. 0/empty = unlimited.
	 *
	 * Distinct from `_wbam_session_limit`, which caps how many times ONE visitor
	 * sees the ad in a session. This caps the ad across the whole site and every
	 * visitor — "run this creative 5,000 times, then stop".
	 */
	const CAP_META = '_wbam_impression_cap';

	/**
	 * Impressions delivered so far against CAP_META.
	 *
	 * Deliberately its own counter rather than a read of the analytics tables.
	 * Analytics recording is conditional — it is skipped when analytics are
	 * disabled, when `track_logged_in` is off, for bots, and without GDPR
	 * consent — so on a members-only site an analytics-derived count can sit at
	 * near zero while the ad has in fact been delivered thousands of times. A
	 * cap that under-counts silently over-delivers, which is the one failure
	 * mode an advertiser will notice.
	 */
	const COUNT_META = '_wbam_impression_count';

	/**
	 * Ads shown on current page.
	 *
	 * @var array
	 */
	private $page_ads = array();

	/**
	 * Initialize.
	 */
	public function init() {
		// Hook to track impressions when ads are rendered.
		add_filter( 'wbam_ad_output', array( $this, 'on_ad_output' ), 5, 2 );

		// Set cookie in footer with all tracked impressions.
		add_action( 'wp_footer', array( $this, 'set_view_cookie' ), 999 );
	}

	/**
	 * Callback for wbam_ad_output filter to track frequency.
	 *
	 * @since 2.3.3
	 *
	 * @param string $output Ad HTML output.
	 * @param int    $ad_id  Ad ID.
	 * @return string Unchanged output.
	 */
	public function on_ad_output( $output, $ad_id ) {
		// Only track if ad actually has output (was rendered).
		if ( ! empty( $output ) && ! empty( $ad_id ) ) {
			$this->track_impression( $ad_id );
			$this->record_delivery( $ad_id );
		}
		return $output;
	}

	/**
	 * Count one delivered impression against the ad's total cap.
	 *
	 * Call this once per impression that actually reached a visitor. Surfaces
	 * that render server-side are handled by on_ad_output(); surfaces that
	 * decide client-side (in-stream video ads, for example) call this when the
	 * creative actually starts.
	 *
	 * No cap set means no write, so uncapped ads cost nothing extra.
	 *
	 * @param int $ad_id Ad ID.
	 * @return void
	 */
	public function record_delivery( $ad_id ) {
		$ad_id = (int) $ad_id;

		if ( $ad_id <= 0 || $this->get_cap( $ad_id ) <= 0 ) {
			return;
		}

		$delivered = $this->get_delivered( $ad_id ) + 1;
		update_post_meta( $ad_id, self::COUNT_META, $delivered );

		if ( $delivered >= $this->get_cap( $ad_id ) ) {
			/**
			 * Fired the moment an ad reaches its total impression cap.
			 *
			 * @param int $ad_id     Ad ID.
			 * @param int $delivered Impressions delivered.
			 */
			do_action( 'wbam_ad_cap_reached', $ad_id, $delivered );
		}
	}

	/**
	 * Total impression cap for an ad. 0 = unlimited.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_cap( $ad_id ) {
		return max( 0, (int) get_post_meta( (int) $ad_id, self::CAP_META, true ) );
	}

	/**
	 * Impressions delivered against the cap.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_delivered( $ad_id ) {
		return max( 0, (int) get_post_meta( (int) $ad_id, self::COUNT_META, true ) );
	}

	/**
	 * Impressions this ad already served before it had a cap.
	 *
	 * Ads are usually already running when someone decides to cap them. Starting
	 * the counter at zero would hand a creative that has served 3,000
	 * impressions a fresh allowance of 5,000 and deliver 8,000 in total, which
	 * is precisely the over-delivery the cap exists to prevent. So the first
	 * time a cap is set we seed the counter from recorded history.
	 *
	 * This is a lower bound, not a true total: analytics recording is skipped
	 * for bots, for logged-in visitors when `track_logged_in` is off, and
	 * without GDPR consent. Under-counting history is the safe direction - it
	 * can only make the ad run longer than its true remaining allowance, never
	 * cut it short below what the advertiser paid for.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function historical_impressions( $ad_id ) {
		global $wpdb;

		$ad_id = (int) $ad_id;

		if ( $ad_id <= 0 ) {
			return 0;
		}

		$table = $wpdb->prefix . 'wbam_analytics';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off read when a cap is first set; result is stored in meta.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE ad_id = %d AND event_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix.
				$ad_id,
				'impression'
			)
		);

		return max( 0, (int) $count );
	}

	/**
	 * Start the cap counter for an ad, seeding it from recorded history.
	 *
	 * Idempotent: once the counter exists it is left alone, so re-saving an ad
	 * never rewinds or double-seeds it.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int The delivered count now on record.
	 */
	public function seed_delivered( $ad_id ) {
		$ad_id = (int) $ad_id;

		$existing = get_post_meta( $ad_id, self::COUNT_META, true );

		if ( '' !== $existing && null !== $existing ) {
			return $this->get_delivered( $ad_id );
		}

		$seed = $this->historical_impressions( $ad_id );
		update_post_meta( $ad_id, self::COUNT_META, $seed );

		return $seed;
	}

	/**
	 * Whether the ad has used up its total impression cap.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function cap_reached( $ad_id ) {
		$cap = $this->get_cap( $ad_id );

		return $cap > 0 && $this->get_delivered( $ad_id ) >= $cap;
	}

	/**
	 * Track ad impression on current page.
	 *
	 * @param int $ad_id Ad ID.
	 */
	public function track_impression( $ad_id ) {
		$this->page_ads[] = $ad_id;
	}

	/**
	 * Get number of ads shown on current page.
	 *
	 * @return int
	 */
	public function get_page_ad_count() {
		return count( array_unique( $this->page_ads ) );
	}

	/**
	 * Check if page limit reached.
	 *
	 * @return bool
	 */
	public function page_limit_reached() {
		$settings = Settings::get_instance();
		$max_page = $settings->get( 'max_ads_per_page', 0 );

		if ( $max_page <= 0 ) {
			return false;
		}

		return $this->get_page_ad_count() >= $max_page;
	}

	/**
	 * Check if specific ad can be shown (session limit).
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function can_show_ad( $ad_id ) {
		// Check page limit first.
		if ( $this->page_limit_reached() ) {
			return false;
		}

		// Total impression cap — the ad is finished for everyone, not just
		// this visitor, so it is checked before any per-session logic.
		if ( $this->cap_reached( $ad_id ) ) {
			return false;
		}

		// Check session limit for this specific ad.
		$session_limit = get_post_meta( $ad_id, '_wbam_session_limit', true );

		if ( empty( $session_limit ) || $session_limit <= 0 ) {
			return true;
		}

		$views = $this->get_ad_views( $ad_id );
		return $views < $session_limit;
	}

	/**
	 * Get ad views from cookie with server-side fallback.
	 *
	 * Uses cookie-based tracking as primary method, with IP-based
	 * transient fallback for when cookies are disabled or cleared.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_ad_views( $ad_id ) {
		$cookie_data  = $this->get_cookie_data();
		$cookie_views = isset( $cookie_data[ $ad_id ] ) ? (int) $cookie_data[ $ad_id ] : 0;

		// Server-side fallback: Check IP-based transient.
		$visitor_hash = $this->get_visitor_hash();
		if ( ! empty( $visitor_hash ) ) {
			$transient_key = 'wbam_freq_' . $visitor_hash . '_' . $ad_id;
			$server_views  = get_transient( $transient_key );
			$server_views  = false !== $server_views ? (int) $server_views : 0;

			// Return the higher count to prevent bypassing limits.
			return max( $cookie_views, $server_views );
		}

		return $cookie_views;
	}

	/**
	 * Get cookie data.
	 *
	 * @return array
	 */
	private function get_cookie_data() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$data = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Generate visitor hash for server-side tracking.
	 *
	 * Uses IP address and User-Agent with daily rotating salt for
	 * GDPR-compliant anonymized tracking.
	 *
	 * @since 2.3.2
	 * @return string Visitor hash or empty string if IP not available.
	 */
	private function get_visitor_hash() {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip_address     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		$user_agent_raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$daily_salt     = wp_hash( gmdate( 'Y-m-d' ) );

		return hash( 'sha256', $ip_address . $user_agent_raw . $daily_salt );
	}

	/**
	 * Set view cookie in footer with server-side fallback.
	 */
	public function set_view_cookie() {
		if ( empty( $this->page_ads ) ) {
			return;
		}

		$cookie_data  = $this->get_cookie_data();
		$visitor_hash = $this->get_visitor_hash();

		foreach ( $this->page_ads as $ad_id ) {
			if ( isset( $cookie_data[ $ad_id ] ) ) {
				++$cookie_data[ $ad_id ];
			} else {
				$cookie_data[ $ad_id ] = 1;
			}

			// Server-side fallback: Update transient for IP-based tracking.
			if ( ! empty( $visitor_hash ) ) {
				$transient_key = 'wbam_freq_' . $visitor_hash . '_' . $ad_id;
				$current_count = get_transient( $transient_key );
				$current_count = false !== $current_count ? (int) $current_count : 0;
				set_transient( $transient_key, $current_count + 1, self::COOKIE_EXPIRATION );
			}
		}

		// Output JS to set cookie.
		$json = wp_json_encode( $cookie_data );
		$exp  = time() + self::COOKIE_EXPIRATION;
		?>
		<script>
		(function() {
			document.cookie = '<?php echo esc_js( self::COOKIE_NAME ); ?>=' + encodeURIComponent('<?php echo esc_js( $json ); ?>') + ';expires=<?php echo esc_js( gmdate( 'D, d M Y H:i:s', $exp ) ); ?> GMT;path=/';
		})();
		</script>
		<?php
	}

	/**
	 * Get ads sorted by priority.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return array Sorted ad IDs.
	 */
	public function sort_by_priority( $ad_ids ) {
		if ( empty( $ad_ids ) ) {
			return array();
		}

		$ads_with_priority = array();

		foreach ( $ad_ids as $ad_id ) {
			$priority                    = get_post_meta( $ad_id, '_wbam_priority', true );
			$ads_with_priority[ $ad_id ] = ! empty( $priority ) ? (int) $priority : 5;
		}

		// Sort by priority (higher = first).
		arsort( $ads_with_priority );

		return array_keys( $ads_with_priority );
	}

	/**
	 * Get random ad from list with weight.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return int|null Selected ad ID or null.
	 */
	public function get_weighted_random( $ad_ids ) {
		if ( empty( $ad_ids ) ) {
			return null;
		}

		$weighted = array();

		foreach ( $ad_ids as $ad_id ) {
			$priority = get_post_meta( $ad_id, '_wbam_priority', true );
			$weight   = ! empty( $priority ) ? (int) $priority : 5;

			// Add ad to pool based on weight.
			for ( $i = 0; $i < $weight; $i++ ) {
				$weighted[] = $ad_id;
			}
		}

		if ( empty( $weighted ) ) {
			return $ad_ids[0];
		}

		return $weighted[ array_rand( $weighted ) ];
	}

	/**
	 * Filter ads by frequency rules.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return array Filtered ad IDs.
	 */
	public function filter_by_frequency( $ad_ids ) {
		$filtered = array();

		foreach ( $ad_ids as $ad_id ) {
			if ( $this->can_show_ad( $ad_id ) ) {
				$filtered[] = $ad_id;
			}
		}

		return $filtered;
	}
}
