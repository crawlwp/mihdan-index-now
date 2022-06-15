<?php
/**
 * Settings class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use Mihdan\IndexNow\Utils;

/**
 * Class Settings.
 */
class Settings {
	/**
	 * WP_OSA instance.
	 *
	 * @var WPOSA $wposa
	 */
	public $wposa;

	/**
	 * HelpTab instance.
	 *
	 * @var HelpTab $help_tab
	 */
	public $help_tab;

	/**
	 * Array of post types.
	 *
	 * @var array $post_types
	 */
	private $post_types;

	/**
	 * @var array $taxonomies
	 */
	private $taxonomies;

	/**
	 * Constructor.
	 *
	 * @param WPOSA   $wposa WPOSA instance.
	 * @param HelpTab $help_tab HelpTab instance.
	 */
	public function __construct( WPOSA $wposa, HelpTab $help_tab ) {
		$this->wposa    = $wposa;
		$this->help_tab = $help_tab;
	}

	/**
	 * Setup vars.
	 */
	public function setup_vars() {
		$args = array(
			'public' => true,
		);

		$this->post_types = wp_list_pluck( get_post_types( $args, 'objects' ), 'label', 'name' );
		$this->taxonomies = wp_list_pluck( get_taxonomies( $args, 'objects' ), 'label', 'name' );
	}

	/**
	 * Get post types.
	 *
	 * @return array
	 */
	private function get_post_types(): array {
		return $this->post_types;
	}

	/**
	 * Get taxonomies.
	 *
	 * @return array
	 */
	private function get_taxonomies(): array {
		return $this->taxonomies;
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks(): void {
		add_action( 'init', [ $this, 'setup_vars' ], 100 );
		add_action( 'init', [ $this, 'setup_fields' ], 101 );
		//register_activation_hook( Utils::get_plugin_file(), [ $this, 'set_default_setting' ] );
	}

	public function set_default_setting(): void {
		$sections = [
			'general' => [
				'post_types'   => [ 'post' => 'post' ],
				'ping_on_post' => 'on',
			],
			'index_now' => [
				'enable'  => 'on',
				'api_key' => Utils::generate_key(),
			],
			'bing_webmaster' => [],
			'yandex_webmaster' => [],
			'google_webmaster' => [],
			'logs' => [],
		];

		foreach ( $sections as $section => $fields ) {
			foreach ( $fields as $key => $value ) {
				//$this->wposa->set_option( $key, $value, $section );
			}
		}
	}

	/**
	 * Setup setting fields.
	 *
	 * @link https://yandex.ru/support/webmaster/indexnow/key.html
	 */
	public function setup_fields() {

		$this->wposa
			->add_sidebar_card(
				[
					'id'    => 'donate',
					'title' => __( 'Enjoyed IndexNow?', 'mihdan-index-now' ),
					'desc'  => __( '<p>Please leave us a <a href="https://wordpress.org/support/plugin/mihdan-index-now/reviews/#new-post" target="_blank" title="Rate &amp; review it">★★★★★</a> rating. We really appreciate your support</p>', 'mihdan-index-now' ),
				]
			)
			->add_sidebar_card(
				[
					'id'    => 'rtfm',
					'title' => __( 'Do you need help?', 'mihdan-index-now' ),
					'desc'  => __( '<p>Here are some available options to help solve your problems.</p><ol><li><a href="https://wordpress.org/plugins/mihdan-index-now/" target="_blank">Plugin home page</a></li><li><a href="https://www.kobzarev.com/projects/index-now/" target="_blank">Plugin docs</a></li><li><a href="https://wordpress.org/support/plugin/mihdan-index-now/" target="_blank">Support forums</a></li><li><a href="https://github.com/mihdan/mihdan-index-now/" target="_blank">Issue tracker</a></li><li><a href="https://t.me/+rBLAx8RzcNk1ZWY6" target="_blank">Help via Telegram</a></li></ol>', 'mihdan-index-now' ),
				]
			);

		$this->wposa->add_section(
			array(
				'id'    => 'general',
				'title' => __( 'General', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'post_types',
				'type'    => 'multicheck',
				'name'    => __( 'Post Types', 'mihdan-index-now' ),
				'options' => $this->get_post_types(),
				'default' => [ 'post' => 'post' ],
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'taxonomies',
				'type'    => 'multicheck',
				'name'    => __( 'Taxonomies', 'mihdan-index-now' ),
				'options' => $this->get_taxonomies(),
				'default' => [ 'category' => 'category' ],
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'ping_when',
				'type'    => 'html',
				'name'    => __( 'Notify SE when', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'ping_on_post',
				'type'    => 'switch',
				'name'    => __( 'Post added', 'mihdan-index-now' ),
				'default' => 'on',
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'ping_on_term',
				'type'    => 'switch',
				'name'    => __( 'Term added', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'ping_on_comment',
				'type'    => 'switch',
				'name'    => __( 'Comment added', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_section(
			array(
				'id'    => 'index_now',
				'title' => __( 'IndexNow', 'mihdan-index-now' ),
				'desc'  => __( 'IndexNow is an easy way for websites owners to instantly inform search engines about latest content changes on their website. In its simplest form, IndexNow is a simple ping so that search engines know that a URL and its content has been added, updated, or deleted, allowing search engines to quickly reflect this change in their search results.', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'index_now',
			array(
				'id'      => 'enable',
				'type'    => 'switch',
				'name'    => __( 'Enable', 'mihdan-index-now' ),
				'default' => 'on',
			)
		);

		$this->wposa->add_field(
			'index_now',
			array(
				'id'          => 'api_key',
				'type'        => 'text',
				'name'        => __( 'API Key', 'mihdan-index-now' ),
				'placeholder' => __( 'Set the API key', 'mihdan-index-now' ),
				'default'     => Utils::generate_key(),
				'help_tab'    => 'index_now_api_key',
				'desc'        => sprintf( '<a style="border-bottom: 1px dotted #2271b1; text-decoration: none; margin-left: 10px;" href="#" onclick="document.getElementById(\'mihdan_index_now_index_now[api_key]\').value=\'%s\'">%s</a>', esc_attr( Utils::generate_key() ), __( 'Show example', 'mihdan-index-now' ) ),
			)
		);

		$this->wposa->add_field(
			'index_now',
			array(
				'id'       => 'search_engine',
				'type'     => 'radio',
				'name'     => __( 'Search Engine', 'mihdan-index-now' ),
				'default'  => 'yandex-index-now',
				'help_tab' => 'search_engine_support',
				'options'  => [
					'bing-index-now'       => __( 'Bing', 'mihdan-index-now' ),
					'index-now'            => __( 'IndexNow', 'mihdan-index-now' ),
					'yandex-index-now'     => __( 'Yandex', 'mihdan-index-now' ),
					'seznam-index-now'     => __( 'Seznam', 'mihdan-index-now' ),
					//'duckduckgo' => __( 'DuckDuckGo', 'mihdan-index-now' ),
					//'google'     => __( 'Google', 'mihdan-index-now' ),
					//'baidu'      => __( 'Baidu', 'mihdan-index-now' ),
				],
			)
		);

		$this->wposa->add_section(
			array(
				'id'    => 'bing_webmaster',
				'title' => __( 'Bing API', 'mihdan-index-now' ),
				'desc'  => __( 'Easy to plug-in API solution that websites can call to notify Bing whenever website contents is updated or created allowing instant crawling, indexing and discovery of your site content.', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'bing_webmaster',
			array(
				'id'   => 'enable',
				'type' => 'switch',
				'name' => __( 'Enable', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'bing_webmaster',
			array(
				'id'          => 'api_key',
				'type'        => 'text',
				'name'        => __( 'API Key', 'mihdan-index-now' ),
				'help_tab'    => 'bing_webmaster_api_key',
				'placeholder' => __( 'Example AQAAAAAAWDmFAAbgvUbjwWHB8EkDoF387hLTUta', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_section(
			array(
				'id'    => 'google_webmaster',
				'title' => __( 'Google API', 'mihdan-index-now' ),
				'desc'  => __( 'Google Instant Indexing API Settings. Submit posts from these post types automatically to the Google Instant Indexing API when a post is published, edited, or deleted.', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'google_webmaster',
			array(
				'id'   => 'enable',
				'type' => 'switch',
				'name' => __( 'Enable', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'google_webmaster',
			array(
				'id'          => 'json_key',
				'type'        => 'textarea',
				'name'        => __( 'Google JSON Key', 'mihdan-index-now' ),
				'placeholder' => __( 'Example AQAAAAAAWDmFAAbgvUbjwWHB8EkDoF387hLTUta', 'mihdan-index-now' ),
				'desc'        => __( 'Paste the Service Account JSON key file contents you obtained from Google API Console in the field.', 'mihdan-index-now' ),
				'help_tab'    => 'google_webmaster_json_key',
			)
		);

		$this->wposa->add_section(
			array(
				'id'       => 'yandex_webmaster',
				'title'    => __( 'Yandex API', 'mihdan-index-now' ),
				'desc'     => __( 'Sending a page for reindexing', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'   => 'enable',
				'type' => 'switch',
				'name' => __( 'Enable', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'client_id',
				'type'        => 'text',
				'help_tab'    => 'yandex_webmaster_authorization',
				'name'        => __( 'App ID', 'mihdan-index-now' ),
				'placeholder' => __( 'Example 12c41fd597854d47b2911716d7f71e2f', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'client_secret',
				'type'        => 'text',
				'help_tab'    => 'yandex_webmaster_authorization',
				'name'        => __( 'App Password', 'mihdan-index-now' ),
				'placeholder' => __( 'Example 1a4c5831b44e469f8a86c36fd88101f5', 'mihdan-index-now' ),
			)
		);

		if ( $this->wposa->get_option( 'access_token', 'yandex_webmaster' ) ) {

			$this->wposa->add_field(
				'yandex_webmaster',
				array(
					'id'      => 'host_id',
					'type'    => 'select',
					'name'    => __( 'Host ID', 'mihdan-index-now' ),
					'options' => $this->get_yandex_webmaster_host_ids()
				)
			);

			$this->wposa->add_field(
				'yandex_webmaster',
				array(
					'id'       => 'access_token_view',
					'type'     => 'html',
					'readonly' => true,
					'name'     => __( 'Access Token', 'mihdan-index-now' ),
					'desc'     => function() {
						return sprintf(
							'Key: %s<br> Expires In: %s',
							$this->wposa->get_option( 'access_token', 'yandex_webmaster' ),
							date( 'd.m.Y', $this->wposa->get_option( 'expires_in', 'yandex_webmaster' ) )
						);
					},
				)
			);
		}

		// Показать кнопку только если заполнены доступы к API.
		if ( $this->wposa->get_option( 'client_id', 'yandex_webmaster' ) && $this->wposa->get_option( 'client_secret', 'yandex_webmaster' ) ) {
			$this->wposa->add_field(
				'yandex_webmaster',
				array(
					'id'          => 'button_get_token',
					'type'        => 'button',
					'name'        => __( '', 'mihdan-index-now' ),
					'placeholder' => $this->wposa->get_option( 'access_token', 'yandex_webmaster' )
						? __( 'Update Token', 'mihdan-index-now' )
						: __( 'Get Token', 'mihdan-index-now' ),
				)
			);
		}

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'access_token',
				'type'        => 'hidden',
				'name'        => '',
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'expires_in',
				'type'        => 'hidden',
				'name'        => '',
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'refresh_token',
				'type'        => 'hidden',
				'name'        => '',
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'user_id',
				'type'        => 'hidden',
				'name'        => '',
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'   => 'host_ids',
				'type' => 'hidden',
				'name' => '',
			)
		);

		$this->wposa->add_section(
			array(
				'id'    => 'logs',
				'title' => __( 'Logs', 'mihdan-index-now' ),
				'desc'  => __( 'Module for logging incoming request from search engine and outgoing request from site.', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'enable',
				'type'    => 'switch',
				'name'    => __( 'Enable', 'mihdan-index-now' ),
				'default' => 'on',
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'key_logging',
				'type'    => 'switch',
				'name'    => __( 'Key logging', 'mihdan-index-now' ),
				'default' => 'on',
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'outgoing_requests',
				'type'    => 'switch',
				'name'    => __( 'Outgoing requests', 'mihdan-index-now' ),
				'default' => 'on',
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'cron_events',
				'type'    => 'switch',
				'name'    => __( 'Cron events', 'mihdan-index-now' ),
				'default' => 'off',
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'bulk_actions',
				'type'    => 'switch',
				'name'    => __( 'Bulk Actions', 'mihdan-index-now' ),
				'default' => 'off',
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'lifetime',
				'type'    => 'number',
				'name'    => __( 'Lifetime', 'mihdan-index-now' ),
				'default' => 1,
				'desc'    => __( 'Logs lifetime in days', 'mihdan-index-now' ),
			)
		);
	}

	public function get_yandex_webmaster_host_ids() {
		$result = [];

		$ids = maybe_unserialize( $this->wposa->get_option( 'host_ids', 'yandex_webmaster' ) );

		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$result[ $id ] = $id;
			}
		}

		return $result;
	}
}
