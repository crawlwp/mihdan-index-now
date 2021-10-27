<?php
namespace Mihdan\IndexNow;

use WP_Post;

class Main {
	/**
	 * Settings instance.
	 *
	 * @var Settings $settings
	 */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function setup_hooks() {
		add_action( 'transition_post_status', [ $this, 'maybe_do_pings' ], 10, 3 );
		add_action( 'admin_init', [ $this->settings, 'setup_fields' ], 1 );
	}

	/**
	 * Fires actions related to the transitioning of a post's status.
	 *
	 * @param string  $new_status Transition to this post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function maybe_do_pings( $new_status, $old_status, WP_Post $post ) {
		// Срабатывает только на статус publish.
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! in_array( $post->post_type, [ 'post', 'page' ] ) ) {
			return;
		}

		$this->ping_with_yandex( $post );
	}

	private function ping_with_yandex( WP_Post $post ) {

		$url = 'https://yandex.com/indexnow';
		$args = array(
			'timeout' => 30,
			'body' => json_encode(
				array(
					'host'        => parse_url( get_home_url(), PHP_URL_HOST ),
					'key'         => $this->settings->wposa->get_option( 'api_key',MIHDAN_INDEX_NOW_PREFIX.'_general' ),
					//'keyLocation' => '',
					'urlList'     => [ get_permalink( $post->ID ) ]
				)
			),
		);

		$response = wp_remote_post( $url, $args );
	}
}