<?php
/**
 * Settings class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

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
	}

	/**
	 * Get post types.
	 *
	 * @return array
	 */
	private function get_post_types() {
		return $this->post_types;
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_action( 'init', [ $this, 'setup_vars' ], 100 );
		add_action( 'init', [ $this, 'setup_fields' ], 101 );
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
					'desc'  => __( '<p>Here are some available options to help solve your problems.</p><ol><li><a href="https://wordpress.org/plugins/mihdan-index-now/" target="_blank">Plugin home page</a></li><li><a href="https://www.kobzarev.com/projects/index-now/" target="_blank">Plugin docs</a></li><li><a href="https://wordpress.org/support/plugin/mihdan-index-now/" target="_blank">Support forums</a></li><li><a href="https://github.com/mihdan/mihdan-index-now/" target="_blank">Issue tracker</a></li></ol>', 'mihdan-index-now' ),
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
				'default' => array( 'post' => 'post' ),
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
				'id'      => 'ping_on_comment',
				'type'    => 'switch',
				'name'    => __( 'Comment added', 'mihdan-index-now' ),
				'default' => 'on',
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
				'default'     => $this->generate_key(),
				'help_tab'    => 'index_now_api_key',
				'desc'        => sprintf( '<a style="border-bottom: 1px dotted #2271b1; text-decoration: none; margin-left: 10px;" href="#" onclick="document.getElementById(\'mihdan_index_now_index_now[api_key]\').value=\'%s\'">%s</a>', esc_attr( $this->generate_key() ), __( 'Show example', 'mihdan-index-now' ) ),
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
					'yandex-index-now'     => __( 'Yandex', 'mihdan-index-now' ),
					'bing-index-now'       => __( 'Bing', 'mihdan-index-now' ),
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
				'id'       => 'yandex_webmaster',
				'title'    => __( 'Yandex API', 'mihdan-index-now' ),
				'desc'     => __( 'Sending a page for reindexing', 'mihdan-index-now' ),
				'disabled' => true,
				'badge'    => __( 'Soon', 'mihdan-index-now' ),
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

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'access_token',
				'type'        => 'text',
				'help_tab'    => 'yandex_webmaster_authorization',
				'name'        => __( 'Access Token', 'mihdan-index-now' ),
				'placeholder' => __( 'Example AQAAAAAAWDmFAAbgvUbjwWHB8EkDoF387hLTUta', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'refresh_token',
				'type'        => 'text',
				'help_tab'    => 'yandex_webmaster_authorization',
				'name'        => __( 'Refresh Token', 'mihdan-index-now' ),
				'placeholder' => __( 'Example AQAAAAAAWDmFAAbgvUbjwWHB8EkDoF387hLTUta', 'mihdan-index-now' ),
			)
		);

		//if ( $this->wposa->get_option( 'token', 'yandex_webmaster' ) ) {
			$this->wposa->add_field(
				'yandex_webmaster',
				array(
					'id'          => 'user_id',
					'type'        => 'text',
					'help_tab'    => 'yandex_webmaster',
					'name'        => __( 'User ID', 'mihdan-index-now' ),
					'placeholder' => __( 'Example 5781893', 'mihdan-index-now' ),
				)
			);

			$this->wposa->add_field(
				'yandex_webmaster',
				array(
					'id'          => 'host_id',
					'type'        => 'select',
					'name'        => __( 'Host ID', 'mihdan-index-now' ),
					'options'     => [
						1=>1,
						2=>2,
					],
				)
			);
	//	}

		$this->wposa->add_section(
			array(
				'id'    => 'google_webmaster',
				'title' => __( 'Google API', 'mihdan-index-now' ),
				'desc'  => __( 'Sending a sitemap.xml for reindexing', 'mihdan-index-now' ),
				'disabled' => true,
				'badge'    => __( 'Soon', 'mihdan-index-now' ),
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

		$this->wposa->add_section(
			array(
				'id'    => 'manual_submission',
				'title' => __( 'Manual submission', 'mihdan-index-now' ),
				//'desc'  => __( 'IndexNow is an easy way for websites owners to instantly inform search engines about latest content changes on their website. In its simplest form, IndexNow is a simple ping so that search engines know that a URL and its content has been added, updated, or deleted, allowing search engines to quickly reflect this change in their search results.', 'mihdan-index-now' ),
				'disabled' => true,
				'badge'    => __( 'Soon', 'mihdan-index-now' ),
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
				'id'      => 'lifetime',
				'type'    => 'number',
				'name'    => __( 'Lifetime', 'mihdan-index-now' ),
				'default' => 1,
				'desc'    => __( 'Logs lifetime in days', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'logs',
			array(
				'id'      => 'types',
				'type'    => 'multicheck',
				'name'    => __( 'Types', 'mihdan-index-now' ),
				'options' => [
					'ping' => __( 'Outgoing: ping', 'mihdan-index-now' ),
					//'url'  => __( 'Incoming: url', 'mihdan-index-now' ),
					'cron' => __( 'Cron events', 'mihdan-index-now' ),
				],
				'default' => [
					'ping' => 'ping',
				],
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
	}

	/**
	 * Generate random key.
	 *
	 * @return string
	 */
	private function generate_key() {
		return str_replace( '-', '', wp_generate_uuid4() );
	}
}
