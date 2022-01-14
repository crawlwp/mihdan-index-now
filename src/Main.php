<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Logger\Logger;
use Mihdan\IndexNow\Providers\Bing\BingIndexNow;
use Mihdan\IndexNow\Providers\Bing\BingWebmaster;
use Mihdan\IndexNow\Providers\IndexNow\IndexNow;
use Mihdan\IndexNow\Providers\Yandex\YandexIndexNow;
use Mihdan\IndexNow\Providers\Yandex\YandexWebmaster;
use Mihdan\IndexNow\Views\HelpTab;
use Mihdan\IndexNow\Views\Log_List_Table;
use Mihdan\IndexNow\Views\Settings;
use Mihdan\IndexNow\Views\WPOSA;
use WP_Post;
use WP_List_Table;
use Auryn\Injector;
use Auryn\InjectionException;
use Auryn\ConfigException;
use WP_Upgrader;

/**
 * Class Main.
 */
class Main {
	/**
	 * DIC container.
	 *
	 * @var Injector $injector
	 */
	private $injector;

	/**
	 * Settings instance.
	 *
	 * @var WPOSA $wposa
	 */
	private $wposa;

	/**
	 * Constructor.
	 *
	 * @param Injector $injector Injector instnace.
	 */
	public function __construct( Injector $injector ) {
		$this->injector = $injector;
	}

	public function init() {
		$this->load_requirements();
		$this->setup_hooks();

		do_action( 'mihdan_index_now/init', $this );
	}

	/**
	 * Make a class from DIC.
	 *
	 * @param string $class_name Full class name.
	 * @param array  $args List of arguments.
	 *
	 * @return mixed
	 *
	 * @throws InjectionException If a cyclic gets detected when provisioning.
	 * @throws ConfigException If $nameOrInstance is not a string or an object.
	 */
	public function make( string $class_name, array $args = [] ) {
		return $this->injector->share( $class_name )->make( $class_name, $args );
	}

	private function load_requirements() {

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$this->make( Logger::class );

		( $this->make( Hooks::class ) )->setup_hooks();

		$wposa = $this->make(
			WPOSA::class,
			[
				':plugin_name'    => Utils::get_plugin_name(),
				':plugin_version' => Utils::get_plugin_version(),
				':plugin_slug'    => Utils::get_plugin_slug(),
				':plugin_prefix'  => Utils::get_plugin_prefix(),
			]
		);

		$this->wposa = $wposa;
		$this->wposa->setup_hooks();

		( $this->make( HelpTab::class ) )->setup_hooks();
		( $this->make( Settings::class ) )->setup_hooks();
		( $this->make( Cron::class ) )->setup_hooks();
		( $this->make( YandexIndexNow::class ) )->setup_hooks();
		( $this->make( BingIndexNow::class ) )->setup_hooks();
		( $this->make( IndexNow::class ) )->setup_hooks();

		( $this->make( YandexWebmaster::class ) )->setup_hooks();
		( $this->make( BingWebmaster::class ) )->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_filter( 'plugin_action_links', [ $this, 'add_settings_link' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'add_log_menu_page' ] );
		add_action( 'template_redirect', [ $this, 'parse_incoming_request' ] );
		add_filter( 'set_screen_option_logs_per_page', [ $this, 'set_screen_option' ], 10, 3 );
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
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
		$this->create_tables();
	}

	private function drop_tables() {
		global $wpdb;

		$sql = "DROP TABLE IF EXISTS {$wpdb->base_prefix}index_now_log";
		$wpdb->query( $sql );
	}

	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->base_prefix}index_now_log (
    			log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    			level enum('emergency','alert','critical','error','warning','notice','info','debug') NOT NULL DEFAULT 'debug',
    			search_engine enum('index-now','yandex-index-now','yandex-webmaster','bing-index-now','bing-webmaster','site') NOT NULL DEFAULT 'site',
    			direction enum('incoming','outgoing','internal') NOT NULL DEFAULT 'incoming',
    			status_code INT(11) unsigned NOT NULL,
    			message text NOT NULL,
    			PRIMARY KEY (log_id)
				) {$charset_collate};";

		dbDelta( $sql );
	}

	public function maybe_upgrade() {
		$db_version     = Utils::get_db_version();
		$plugin_version = Utils::get_plugin_version();

		if ( version_compare( $db_version, '2.0.0', '<' ) ) {
			$this->migrate_to_2_0_0();
		}
	}

	private function migrate_to_2_0_0() {
		$this->drop_tables();
		$this->create_tables();
		Utils::set_db_version( '2.0.0' );
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
				$GLOBALS[ MIHDAN_INDEX_NOW_PREFIX . '_log' ] = $this->make( Log_List_Table::class );
			}
		);
	}

	/**
	 * Render log menu page for dashboard.
	 */
	public function render_log_page() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( get_admin_page_title(), 'mihdan-index-now' ); ?></h2>
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
		if ( Utils::get_plugin_basename() === $plugin_file ) {
			$actions[] = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=' . Utils::get_plugin_slug() ),
				esc_html__( 'Settings', 'mihdan-index-now' )
			);
		}

		return $actions;
	}

	private function is_logging_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'logs', 'on' ) === 'on';
	}

	/**
	 * Parse incoming request.
	 */
	public function parse_incoming_request() { return;

		if ( ! in_array( Utils::get_user_agent(), $this->get_bots(), true ) ) {
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

		$this->logger->debug( 'Бот запросил страницу<br>' . Utils::get_user_agent(), $data );
	}

	/**
	 * Google Webmaster ping.
	 *
	 * @param WP_Post $post WP_Post unstance.
	 */
	public function google_webmaster_ping( WP_Post $post ) {
		$url = 'https://www.google.com/webmasters/sitemaps/ping?sitemap=%s';
		$url = sprintf( $url, site_url( 'sitemap_index.xml' ) );
		wp_remote_get( $url );

		$url = sprintf( $url, site_url( 'sitemap.xml' ) );
		wp_remote_get( $url );
	}
}
