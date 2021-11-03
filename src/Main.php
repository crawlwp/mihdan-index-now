<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Views\Log;
use WP;
use WP_Post;
use WP_List_Table;

/**
 * Class Main.
 */
class Main {
	/**
	 * Logger instance.
	 *
	 * @var Logger $logger
	 */
	private $logger;

	/**
	 * Settings instance.
	 *
	 * @var Settings $settings
	 */
	private $settings;

	/**
	 * API key.
	 *
	 * @var string $api_key
	 */
	private $api_key;

	/**
	 * Site host name.
	 *
	 * @var Settings $host
	 */
	private $host;

	/**
	 * Array to store the instances of available indexnow complaint search engines.
	 *
	 * @var array $search_engines
	 */
	private $search_engines;

	/**
	 * Constructor.
	 *
	 * @param Logger   $logger Logger instnace.
	 * @param Settings $settings Settings instnace.
	 */
	public function __construct( Logger $logger, Settings $settings ) {
		$this->logger         = $logger;
		$this->host           = wp_parse_url( get_home_url(), PHP_URL_HOST );
		$this->settings       = $settings;
		$this->api_key        = $this->settings->wposa->get_option( 'api_key', MIHDAN_INDEX_NOW_PREFIX . '_general' );
		$this->search_engines = apply_filters( 'mihdan_index_now/search_engines', $this->settings->wposa->get_option( 'search_engines', MIHDAN_INDEX_NOW_PREFIX . '_general', [] ) );
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_action( 'publish_post', [ $this, 'maybe_do_pings' ], 10, 2 );
		add_action( 'publish_page', [ $this, 'maybe_do_pings' ], 10, 2 );
		add_action( 'publish_product', [ $this, 'maybe_do_pings' ], 10, 2 );

		add_action( 'parse_request', [ $this, 'set_virtual_key_file' ] );
		add_filter( 'plugin_action_links', [ $this, 'add_settings_link' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'add_log_menu_page' ] );

		add_filter( 'set_screen_option_logs_per_page', [ $this, 'set_screen_option' ], 10, 3 );

		register_activation_hook( MIHDAN_INDEX_NOW_FILE, [ $this, 'activate_plugin' ] );
	}

	/**
	 * Set screen option.
	 *
	 * @param string $status Status.
	 * @param string $option Option name.
	 * @param string $value  Option value.
	 *
	 * @return int
	 */
	public function set_screen_option( $status, $option, $value ) {
		return (int) $value;
	}

	/**
	 * Fired on plugin activate.
	 */
	public function activate_plugin() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->base_prefix}index_now_log (
    			log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    			post_id bigint(20) UNSIGNED NOT NULL,
    			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    			level enum('emergency','alert','critical','error','warning','notice','info','debug') NOT NULL DEFAULT 'debug',
    			search_engine enum('yandex','bing','duckduck','cloudflare') NOT NULL DEFAULT 'yandex',
    			direction enum('incoming','outgoing') NOT NULL DEFAULT 'incoming',
    			status_code INT(11) unsigned NOT NULL,
    			message text NOT NULL,
    			PRIMARY KEY (log_id)
				) {$charset_collate};";

		$result = dbDelta( $sql );
	}

	/**
	 * Add log menu page for dashboard.
	 */
	public function add_log_menu_page() {
		$hook = add_submenu_page(
			MIHDAN_INDEX_NOW_SLUG,
			'Log',
			'Log',
			'manage_options',
			MIHDAN_INDEX_NOW_SLUG . '-log',
			[ $this, 'render_log_page' ]
		);

		add_action(
			"load-$hook",
			function () {
				$GLOBALS[ MIHDAN_INDEX_NOW_PREFIX . '_log' ] = new Log();
			}
		);
	}

	/**
	 * Render log menu page for dashboard.
	 */
	public function render_log_page() {
		?>
		<div class="wrap">
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<form action="" method="post">
				<?php
				/**
				 * WP_List_table.
				 *
				 * @var WP_List_Table $table
				 */
				$table = $GLOBALS[ MIHDAN_INDEX_NOW_PREFIX . '_log' ];
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add plugin action links
	 *
	 * @param array  $actions Default actions.
	 * @param string $plugin_file Plugin file.
	 *
	 * @return array
	 */
	public function add_settings_link( $actions, $plugin_file ) {
		if ( MIHDAN_INDEX_NOW_BASENAME === $plugin_file ) {
			$actions[] = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ),
				esc_html__( 'Settings', 'mihdan-index-now' )
			);
		}

		return $actions;
	}

	/**
	 * Set virtual key file.
	 *
	 * @param WP $wp WP instance.
	 */
	public function set_virtual_key_file( WP $wp ) {
		$api_key = $this->get_api_key();

		if ( $wp->request !== $api_key . '.txt' ) {
			return;
		}
		header( 'Content-Type: text/plain' );
		header( 'X-Robots-Tag: noindex' );
		status_header( 200 );
		echo esc_html( $api_key );
		die;
	}

	/**
	 * Fires actions related to the transitioning of a post's status.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function maybe_do_pings( $post_id, WP_Post $post ) {

		$search_engines = $this->get_search_engines();

		if ( in_array( 'yandex', $search_engines, true ) ) {
			$this->ping_with_yandex( $post );
		}

		if ( in_array( 'bing', $search_engines, true ) ) {
			$this->ping_with_bing( $post );
		}
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Get host name.
	 *
	 * @return string
	 */
	private function get_host() {
		return $this->host;
	}

	/**
	 * Get search engines.
	 *
	 * @return string
	 */
	private function get_search_engines() {
		return $this->search_engines;
	}

	/**
	 * Ping Yandex.
	 *
	 * @param WP_Post $post WP_Post instance.
	 */
	private function ping_with_yandex( WP_Post $post ) {

		$url  = 'https://yandex.com/indexnow';
		$args = array(
			'timeout' => 30,
			'body'    => wp_json_encode(
				array(
					'host'    => $this->get_host(),
					'key'     => $this->get_api_key(),
					'urlList' => [
						get_permalink( $post->ID ),
					],
				)
			),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		);

		$response    = wp_remote_post( $url, $args );
		$status_code = wp_remote_retrieve_response_code( $response );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$translate = Logger::ERRORS;

		$data = [
			'post_id'       => $post->ID,
			'status_code'   => $status_code,
			'search_engine' => 'yandex',
		];

		if ( $status_code === 200 ) {
			$message = 'OK';
			$this->logger->debug( $message, $data );
		} else {
			$message = $translate[ $body['message'] ] ?? $body['message'];
			$this->logger->error( $message, $data );
		}
	}

	/**
	 * Ping Bing.
	 *
	 * @param WP_Post $post WP_Post instance.
	 */
	private function ping_with_bing( WP_Post $post ) {

		$url  = 'https://www.bing.com/indexnow';
		$args = array(
			'timeout' => 30,
			'body'    => wp_json_encode(
				array(
					'host'    => $this->get_host(),
					'key'     => $this->get_api_key(),
					'urlList' => [
						get_permalink( $post->ID ),
					],
				)
			),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		);

		$response    = wp_remote_post( $url, $args );
		$status_code = wp_remote_retrieve_response_code( $response );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$translate = Logger::ERRORS;

		$data = [
			'post_id'       => $post->ID,
			'status_code'   => $status_code,
			'search_engine' => 'bing',
		];

		if ( $status_code === 200 ) {
			$message = 'OK';
			$this->logger->debug( $message, $data );
		} else {
			$message = $translate[ $body['message'] ] ?? $body['message'];
			$this->logger->error( $message, $data );
		}
	}
}
