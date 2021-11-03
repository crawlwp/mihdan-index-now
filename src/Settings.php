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
	 * Array of post types.
	 *
	 * @var array $post_types
	 */
	private $post_types;

	/**
	 * Constructor.
	 *
	 * @param WPOSA $wposa WPOSA instance.
	 */
	public function __construct( WPOSA $wposa ) {
		$this->wposa = $wposa;
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
		$this->wposa->add_section(
			array(
				'id'    => MIHDAN_INDEX_NOW_PREFIX . '_general',
				'title' => __( 'General', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			MIHDAN_INDEX_NOW_PREFIX . '_general',
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
			MIHDAN_INDEX_NOW_PREFIX . '_general',
			array(
				'id'   => 'api_key_help',
				'type' => 'html',
				'name' => __( 'Требования к ключу', 'mihdan-index-now' ),
				'desc' => '<ul><li>Поддерживается только кодировка UTF-8</li><li>Минимальное количество символов в ключе — 8, максимальное — 128.</li><li>Ключ может содержать символы <code>a-z</code>, <code>A-Z</code> , <code>0-9</code>, <code>-</code>.</li></ul>',
			)
		);

		$this->wposa->add_field(
			MIHDAN_INDEX_NOW_PREFIX . '_general',
			array(
				'id'      => 'search_engines',
				'type'    => 'multicheck',
				'name'    => __( 'Search Engines', 'mihdan-index-now' ),
				'default' => [ 'yandex' => 'yandex' ],
				'options' => [
					'yandex'     => __( 'Yandex', 'mihdan-index-now' ),
					'bing'       => __( 'Bing', 'mihdan-index-now' ),
					'cloudflare' => __( 'Cloudflare', 'mihdan-index-now' ),
					'duckduckgo' => __( 'DuckDuckGo', 'mihdan-index-now' ),
				],
			)
		);

		$this->wposa->add_field(
			MIHDAN_INDEX_NOW_PREFIX . '_general',
			array(
				'id'      => 'post_types',
				'type'    => 'multicheck',
				'name'    => __( 'Post Types', 'mihdan-mailru-pulse-feed' ),
				'options' => $this->get_post_types(),
				'default' => array( 'post' => 'post' ),
			)
		);

		$this->wposa->add_section(
			array(
				'id'    => MIHDAN_INDEX_NOW_PREFIX . '_contacts',
				'title' => __( 'Contacts', 'mihdan-index-now' ),
			)
		);

		$this->wposa->add_field(
			MIHDAN_INDEX_NOW_PREFIX . '_contacts',
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
