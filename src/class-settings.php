<?php
namespace Mihdan\IndexNow;

class Settings {
	/**
	 * WP_OSA instance.
	 *
	 * @var WPOSA $wposa
	 */
	public $wposa;

	public function __construct( WPOSA $wposa ) {
		$this->wposa = $wposa;
	}

	public function setup_fields() {
		$this->wposa->add_section(
			array(
				'id'    => MIHDAN_INDEX_NOW_PREFIX . '_general',
				'title' => __( 'General', 'mihdan-index-now' ),
			)
		);

		/**
		 * @link https://yandex.ru/support/webmaster/indexnow/key.html
		 */
		$this->wposa->add_field(
			MIHDAN_INDEX_NOW_PREFIX . '_general',
			array(
				'id'          => 'api_key',
				'type'        => 'text',
				'name'        => __( 'API Key', 'mihdan-index-now' ),
				'placeholder' => 'EdD8dkmdNLlxREi2LkhJjYOH2kyQbJqM3cBKT5fX',
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
	}
}