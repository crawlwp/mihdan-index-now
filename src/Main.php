<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Views\Log;
use WP;
use WP_Comment;
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
	private $search_engines = [
		'yandex'     => [
			'url' => 'https://yandex.com/indexnow',
		],
		'bing'       => [
			'url' => 'https://www.bing.com/indexnow',
		],
		'duckduckgo' => [
			'url' => 'https://www.bing.com/indexnow',
		],
	];

	/**
	 * White list of bots.
	 *
	 * @var string[]
	 */
	private $bots = [
		'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
		'Mozilla/5.0 (compatible; YandexAccessibilityBot/3.0; +http://yandex.com/bots)',
		'Mozilla/5.0 (compatible; YandexMetrika/2.0; +http://yandex.com/bots yabs01)',
		'Mozilla/5.0 (compatible; Linux x86_64; Mail.RU_Bot/Img/2.0; +http://go.mail.ru/help/robots)',
		'Mozilla/5.0 (compatible; Linux x86_64; Mail.RU_Bot/2.0; +http://go.mail.ru/help/robots)',
		'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
		'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
	];

	/**
	 * Constructor.
	 *
	 * @param Logger   $logger Logger instnace.
	 * @param Settings $settings Settings instnace.
	 */
	public function __construct( Logger $logger, Settings $settings ) {
		$this->logger         = $logger;
		$this->host           = apply_filters( 'mihdan_index_now/host', wp_parse_url( get_home_url(), PHP_URL_HOST ) );
		$this->settings       = $settings;
		$this->api_key        = $this->settings->wposa->get_option( 'api_key', MIHDAN_INDEX_NOW_PREFIX . '_general' );
		$this->search_engines = apply_filters( 'mihdan_index_now/search_engines', $this->search_engines );
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_action( 'transition_post_status', [ $this, 'ping_on_post_update' ], 10, 3 );
		add_action( 'wp_insert_comment', [ $this, 'ping_on_insert_comment' ], 10, 2 );
		add_action( 'parse_request', [ $this, 'set_virtual_key_file' ] );
		add_filter( 'plugin_action_links', [ $this, 'add_settings_link' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'add_log_menu_page' ] );
		add_action( 'template_redirect', [ $this, 'parse_incoming_request' ] );
		add_filter( 'set_screen_option_logs_per_page', [ $this, 'set_screen_option' ], 10, 3 );
		add_action( 'after_delete_post', [ $this, 'maybe_delete_log_entries' ], 10, 2 );
		register_activation_hook( MIHDAN_INDEX_NOW_FILE, [ $this, 'activate_plugin' ] );
	}

	/**
	 * Delete log entries fired when on post delete.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Instance of WP_Post.
	 */
	public function maybe_delete_log_entries( int $post_id, WP_Post $post ) {
		global $wpdb;

		$wpdb->delete( Logger::get_table_name(), [ 'post_id' => $post_id ] );
	}

	public function ping_on_insert_comment( int $id, WP_Comment $comment ) {
		$this->maybe_do_ping( $comment->comment_post_ID );
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
    			search_engine enum('yandex','bing','duckduck','duckduckgo','cloudflare') NOT NULL DEFAULT 'yandex',
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

		if ( ! $this->is_logging_enabled() ) {
			return;
		}

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

		$log_types = $this->get_log_types();

		if ( ! empty( $log_types['key'] ) && in_array( $this->get_user_agent(), $this->get_bots(), true ) ) {
			$data = [
				'post_id'       => 0,
				'status_code'   => 200,
				'search_engine' => $this->get_search_engine(),
				'direction'     => 'incoming',
			];

			$this->logger->debug( __( 'Бот проверил файл ключа', 'mihdan-index-now' ), $data );
		}

		header( 'Content-Type: text/plain' );
		header( 'X-Robots-Tag: noindex' );
		status_header( 200 );
		echo esc_html( $api_key );
		die;
	}

	/**
	 * Get white list of bots.
	 *
	 * @return string[]
	 */
	private function get_bots(): array {
		return $this->bots;
	}

	/**
	 * Get user agent of browser/bot.
	 *
	 * @return mixed|string
	 */
	private function get_user_agent(): string {
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}

	/**
	 * Fires actions related to the transitioning of a post's status.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post    Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function ping_on_post_update( $new_status, $old_status, WP_Post $post ) {

		if ( $new_status !== 'publish' ) {
			return;
		}

		if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$this->maybe_do_ping( $post->ID );
	}

	private function maybe_do_ping( int $post_id ) {
		$post = get_post( $post_id );

		$post_types = (array) $this->settings->wposa->get_option( 'post_types', MIHDAN_INDEX_NOW_PREFIX . '_general' );

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$search_engines = $this->get_search_engines();
		$search_engine  = $this->get_search_engine();

		if ( ! isset( $search_engines[ $search_engine ] ) ) {
			return;
		}

		if ( $this->is_index_now_enabled() ) {
			$this->do_ping( $search_engine, $search_engines[ $search_engine ]['url'], $post );
		}

		if ( $this->is_yandex_webmaster_enabled() ) {
			$this->yandex_webmaster_ping( $post );
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

	private function get_search_engine() {
		return $this->settings->wposa->get_option( 'search_engine', 'general', 'yandex' );
	}

	private function is_index_now_enabled(): bool {
		return $this->settings->wposa->get_option( 'enable', 'general', 'on' ) === 'on';
	}

	private function is_yandex_webmaster_enabled(): bool {
		return $this->settings->wposa->get_option( 'enable', 'yandex_webmaster', 'off' ) === 'on';
	}

	private function is_logging_enabled(): bool {
		return $this->settings->wposa->get_option( 'enable', 'logs', 'on' ) === 'on';
	}

	private function get_log_types(): array {
		return $this->settings->wposa->get_option( 'types', 'logs', ['ping'] );
	}

	/**
	 * Ping Yandex.
	 *
	 * @param string  $search_engine  Search engine.
	 * @param string  $url            Base URL for ping.
	 * @param WP_Post $post           WP_Post instance.
	 *
	 * @return bool
	 */
	private function do_ping( string $search_engine, string $url, WP_Post $post ): bool {

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
			'search_engine' => $search_engine,
		];

		if ( $status_code === 200 ) {
			$message = 'OK';
			$this->logger->debug( $message, $data );
		} else {
			$message = $body['message'] ?? '';
			$message = $translate[ $message ] ?? $message;
			$this->logger->error( $message, $data );
		}

		return true;
	}

	/**
	 * Parse incoming request.
	 */
	public function parse_incoming_request() {

		$log_types = $this->get_log_types();

		if ( ! isset( $log_types['url'] ) || ! in_array( $this->get_user_agent(), $this->get_bots(), true ) ) {
			return;
		}

		$actual_link = ( is_ssl() ? "https" : "http" ) . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$post_id     = url_to_postid( $actual_link );

		if ( $post_id === 0 ) {
			return;
		}

		$data = [
			'post_id'       => $post_id,
			'status_code'   => 200,
			'search_engine' => 'yandex',
			'direction'     => 'incoming',
		];

		$this->logger->debug( 'Бот запросил страницу<br>' . $this->get_user_agent(), $data );
	}

	/**
	 * Yandex Eebmaster ping.
	 *
	 * @param WP_Post $post WP_Post unstance.
	 *
	 * @link https://yandex.com/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function yandex_webmaster_ping( WP_Post $post ) {
		$user_id = $this->settings->wposa->get_option( 'user_id', 'yandex_webmaster' );
		$host_id = $this->settings->wposa->get_option( 'host_id', 'yandex_webmaster' );
		$token   = $this->settings->wposa->get_option( 'token', 'yandex_webmaster' );

		$url = 'https://api.webmaster.yandex.net/v4/user/%s/hosts/%s/recrawl/queue';
		$url = sprintf( $url, $user_id, $host_id );

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'OAuth ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'url' => get_permalink( $post->ID ),
				)
			),
		);

		$response = wp_remote_post( $url, $args );
	}
}
