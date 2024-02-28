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
use Mihdan\IndexNow\Providers\Google\GoogleWebmaster;
use Mihdan\IndexNow\Providers\IndexNow\IndexNow;
use Mihdan\IndexNow\Providers\Seznam\SeznamIndexNow;
use Mihdan\IndexNow\Providers\Naver\NaverIndexNow;
use Mihdan\IndexNow\Providers\Yandex\YandexIndexNow;
use Mihdan\IndexNow\Providers\Yandex\YandexWebmaster;
use Mihdan\IndexNow\Views\HelpTab;
use Mihdan\IndexNow\Views\Log_List_Table;
use Mihdan\IndexNow\Views\Settings;
use Mihdan\IndexNow\Views\WPOSA;
use WP_Post;
use WP_List_Table;
use Mihdan\IndexNow\Dependencies\Auryn\Injector;
use Mihdan\IndexNow\Dependencies\Auryn\InjectionException;
use Mihdan\IndexNow\Dependencies\Auryn\ConfigException;
use WP_Site;

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
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

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

		$this->logger = $this->make( Logger::class );

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

		( $this->make( Hooks::class ) )->setup_hooks();

		( $this->make( HelpTab::class ) )->setup_hooks();
		( $this->make( Settings::class ) )->setup_hooks();
		( $this->make( Cron::class ) )->setup_hooks();
		( $this->make( YandexIndexNow::class ) )->setup_hooks();
		( $this->make( BingIndexNow::class ) )->setup_hooks();
		( $this->make( SeznamIndexNow::class ) )->setup_hooks();
		( $this->make( NaverIndexNow::class ) )->setup_hooks();
		( $this->make( IndexNow::class ) )->setup_hooks();

		( $this->make( YandexWebmaster::class ) )->setup_hooks();
		( $this->make( BingWebmaster::class ) )->setup_hooks();
		( $this->make( GoogleWebmaster::class ) )->setup_hooks();
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

		/** @todo */
		//add_filter( 'post_row_actions', [ $this, 'post_row_actions' ], 10, 2 );
		//add_filter( 'page_row_actions', [ $this, 'post_row_actions' ], 10, 2 );

		// Add last update column.
		if ( $this->wposa->get_option( 'show_last_update_column', 'general', 'on' ) === 'on' ) {
			foreach ( (array) $this->wposa->get_option( 'post_types', 'general', [] ) as $post_type ) {
				add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_last_update_column' ] );
				add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'add_last_update_column_content' ], 10, 2 );
			}

			add_action( 'admin_head', [ $this, 'add_css_for_column' ] );
		}

		register_activation_hook( MIHDAN_INDEX_NOW_FILE, [ $this, 'activate_plugin' ] );

		// Multisite.
		add_action( 'wp_delete_site', [ $this, 'delete_site_tables' ] );
		add_action( 'wp_insert_site', [ $this, 'add_site_tables' ] );
	}

	/**
	 * Delete site tables when deleting a site.
	 *
	 * @param WP_Site $old_site Site ID.
	 * @return void
	 */
	public function delete_site_tables( WP_Site $old_site ): void {
		switch_to_blog( $old_site->id );
		$this->drop_tables();
		restore_current_blog();
	}

	/**
	 * Add site tables when creating a site.
	 *
	 * @param WP_Site $new_site Site ID.
	 * @return void
	 */
	public function add_site_tables( WP_Site $new_site ): void {
		switch_to_blog( $new_site->id );
		$this->create_tables();
		restore_current_blog();
	}

	public function add_css_for_column(): void {
		?>
		<style>
			.column-index-now {
				width: 8em;
			}
			.column-index-now img {
				vertical-align: bottom;
			}
		</style>
		<?php
	}

	public function add_last_update_column( array $columns ): array {
		$columns['index-now'] = sprintf(
			'<span class="dashicons dashicons-share" title="%s"></span>',
			__( 'IndexNow: Last Update', 'mihdan-index-now' )
		);

		return $columns;
	}

	public function add_last_update_column_content( string $column_name, int $post_id ): void {
		if ( $column_name !== 'index-now' ) {
			return;
		}

		$last_update = (int) get_post_meta( $post_id, Utils::get_plugin_prefix() . '_last_update', true );

		if ( $last_update === 0 ) {
			return;
		}

		echo esc_html( date( 'd.m.Y H:i', $last_update ) );
	}

	public function post_row_actions( array $actions, WP_Post $post ): array {
		if ( ! in_array( $post->post_type, (array) $this->wposa->get_option( 'post_types', 'general', [] ), true ) ) {
			return $actions;
		}

		if ( ! is_post_publicly_viewable( $post ) ) {
			return $actions;
		}

		$actions['index_now'] = sprintf(
			'<a title="%s" href="%s">IndexNow</a>',
			esc_attr( __( 'Notify the search engine', 'mihdan-index-now' ) ),
			1
		);

		return $actions;
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
	public function set_screen_option( $status, $option, $value ): int {
		return (int) $value;
	}

	/**
	 * Fired on plugin activate.
	 */
	public function activate_plugin( $network_wide ) {
		global $wpdb;

		if ( is_multisite() && $network_wide ) {
			$sites = get_sites( [ 'fields' => 'ids' ] );
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				$this->create_tables();
				restore_current_blog();
			}
		} else {
			$this->create_tables();
		}
	}

	private function drop_tables() {
		global $wpdb;

		$sql = "DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log";
		$wpdb->query( $sql );
	}

	private function create_tables( bool $upgrade = false ) {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'index_now_log';
		$charset_collate = $wpdb->get_charset_collate();

		if ( $upgrade || $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			$sql = "CREATE TABLE {$wpdb->prefix}index_now_log (
    			log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    			level enum('emergency','alert','critical','error','warning','notice','info','debug') NOT NULL DEFAULT 'debug',
    			search_engine enum('index-now','yandex-index-now','yandex-webmaster','bing-index-now','bing-webmaster','site','google-webmaster','seznam-index-now','naver-index-now') NOT NULL DEFAULT 'site',
    			direction enum('incoming','outgoing','internal') NOT NULL DEFAULT 'incoming',
    			status_code INT(11) unsigned NOT NULL,
    			message text NOT NULL,
    			PRIMARY KEY (log_id)
				) {$charset_collate};";

			dbDelta($sql);

			Utils::set_db_version( Utils::get_plugin_version() );
		}
	}

	public function maybe_upgrade() {
		$db_version     = Utils::get_db_version();
		$plugin_version = Utils::get_plugin_version();

		if ( version_compare( $db_version, $plugin_version, '<' ) ) {
			$this->create_tables( true );
		}
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
