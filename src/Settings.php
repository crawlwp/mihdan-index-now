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
	}

	/**
	 * Generate random key.
	 *
	 * @param int $length Key Length.
	 * @param string $list List of symbols.
	 *
	 * @return string
	 */
	private function generate_key( $length = 32 , $list = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' ) {
		mt_srand( (double) microtime() * 1000000 );
		$result = '';

		if ( $length > 0 ) {
			while ( strlen( $result ) < $length ) {
				$result .= $list[ mt_rand( 0, strlen( $list ) - 1 ) ];
			}
		}

		return $result;
	}
}
