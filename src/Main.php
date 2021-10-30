<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use WP;
use WP_Post;

/**
 * Class Main.
 */
class Main {
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
	private $search_engines;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instnace.
	 */
	public function __construct( Settings $settings ) {
		$this->host           = wp_parse_url( get_home_url(), PHP_URL_HOST );
		$this->settings       = $settings;
		$this->api_key        = $this->settings->wposa->get_option( 'api_key', MIHDAN_INDEX_NOW_PREFIX . '_general' );
		$this->search_engines = apply_filters( 'mihdan_index_now/search_engines', $this->settings->wposa->get_option( 'search_engines', MIHDAN_INDEX_NOW_PREFIX . '_general', [] ) );
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_action( 'transition_post_status', [ $this, 'maybe_do_pings' ], 10, 3 );
		add_action( 'admin_init', [ $this->settings, 'setup_fields' ], 1 );
		add_action( 'parse_request', [ $this, 'set_virtual_key_file' ] );
		add_filter( 'plugin_action_links', [ $this, 'add_settings_link' ], 10, 2 );
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
				admin_url( 'options-general.php?page=' . MIHDAN_INDEX_NOW_SLUG ),
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
		header( 'Content-Type: text/plain' );
		header( 'X-Robots-Tag: noindex' );
		status_header( 200 );
		echo esc_html( $api_key );
		die;
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
		if ( 'publish' !== $new_status || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
			return;
		}

		$search_engines = $this->get_search_engines();

		if ( in_array( 'yandex', $search_engines, true ) ) {
			$this->ping_with_yandex( $post );
		}

		if ( in_array( 'bing', $search_engines, true ) ) {
			$this->ping_with_bing( $post );
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

	/**
	 * Ping Yandex.
	 *
	 * @param WP_Post $post WP_Post instance.
	 */
	private function ping_with_yandex( WP_Post $post ) {

		$url  = 'https://yandex.com/indexnow';
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

		$response = wp_remote_post( $url, $args );
	}

	/**
	 * Ping Bing.
	 *
	 * @param WP_Post $post WP_Post instance.
	 */
	private function ping_with_bing( WP_Post $post ) {

		$url  = 'https://www.bing.com/indexnow';
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

		$response = wp_remote_post( $url, $args );
	}
}
