<?php
/**
 * Settings class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

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
					'desc'  => __( '<p>Here are some available options to help solve your problems.</p><ol><li><a href="https://wordpress.org/support/plugin/mihdan-index-now/" target="_blank">Support forums</a></li><li><a href="https://github.com/mihdan/mihdan-index-now/issues/new" target="_blank">Issue tracker</a></li></ol>', 'mihdan-index-now' ),
				]
			);

		$this->wposa->add_section(
			array(
				'id'    => 'general',
				'title' => __( 'General', 'mihdan-index-now' ),
				'desc'  => __( 'Your key should have a minimum of 8 and a maximum of 128 hexadecimal characters.<br />The key can contain only the following characters:<br />lowercase characters (a-z), uppercase characters (A-Z), numbers (0-9), and dashes (-).', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'enable',
				'type'    => 'checkbox',
				'name'    => __( 'Enable', 'mihdan-index-now' ),
				'desc'    => __( 'Enable this module', 'mihdan-index-now' ),
				'default' => 'on',
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'          => 'api_key',
				'type'        => 'text',
				'name'        => __( 'API Key', 'mihdan-index-now' ),
				'placeholder' => __( 'Впишите ваш ключ', 'mihdan-index-now' ),
				'default'     => $this->generate_key(),
				'desc'        => sprintf( '<a style="border-bottom: 1px dotted #2271b1; text-decoration: none; margin-left: 10px;" href="#" onclick="document.getElementById(\'mihdan_index_now_general[api_key]\').value=\'%s\'">%s</a>', esc_attr( $this->generate_key() ), __( 'Вставить пример', 'mihdan-index-now' ) ),
			)
		);

		$this->wposa->add_field(
			'general',
			array(
				'id'      => 'search_engine',
				'type'    => 'radio',
				'name'    => __( 'Search Engine', 'mihdan-index-now' ),
				'default' => 'yandex',
				'options' => [
					'yandex'     => __( 'Yandex', 'mihdan-index-now' ),
					'bing'       => __( 'Bing', 'mihdan-index-now' ),
					'duckduckgo' => __( 'DuckDuckGo', 'mihdan-index-now' ),
				],
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

		$this->wposa->add_section(
			array(
				'id'    => 'yandex_webmaster',
				'title' => __( 'Yandex Recrawl', 'mihdan-index-now' ),
				'desc'  => __( 'Sending a page for reindexing', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'   => 'enable',
				'type' => 'checkbox',
				'name' => __( 'Enable', 'mihdan-index-now' ),
				'desc' => __( 'Enable this module', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'user_id',
				'type'        => 'text',
				//'help_tab'    => 'yandex_webmaster',
				'name'        => __( 'User ID', 'mihdan-index-now' ),
				'placeholder' => __( 'Example 5781893', 'mihdan-index-now' ),
				'desc'        => __( 'To get it, use the <code>GET /v4/user</code> method.', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'host_id',
				'type'        => 'text',
				'name'        => __( 'Host ID', 'mihdan-index-now' ),
				'placeholder' => __( 'Example https:kobzarev.com:443', 'mihdan-index-now' ),
				'desc'        => __( 'To get it, use the <code>GET /v4/user/{user-id}/hosts</code> method.', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'yandex_webmaster',
			array(
				'id'          => 'token',
				'type'        => 'text',
				'name'        => __( 'Token', 'mihdan-index-now' ),
				'placeholder' => __( 'Example AQAAAAAAWDmFAAbgvUbjwWHB8EkDoF387hLTUta', 'mihdan-index-now' ),
				'desc'        => __( 'User identifier', 'mihdan-index-now' ),
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
				'type'    => 'checkbox',
				'name'    => __( 'Enable', 'mihdan-index-now' ),
				'desc'    => __( 'Enable this module', 'mihdan-index-now' ),
				'default' => 'on',
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
					'url'  => __( 'Incoming: url', 'mihdan-index-now' ),
					'key'  => __( 'Incoming: key', 'mihdan-index-now' ),
				],
				'default' => [
					'ping' => 'ping',
				],
			)
		);

		$this->wposa->add_section(
			array(
				'id'    => 'contacts',
				'title' => __( 'Contacts', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			'contacts',
			array(
				'id'   => 'description',
				'type' => 'html',
				'name' => __( 'Telegram', 'mihdan-index-now' ),
				'desc' => __( 'Связаться со мной можно в телеграм <a href="https://t.me/mihdan" target="_blank">@mihdan</a>', 'mihdan-index-now' ),
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
