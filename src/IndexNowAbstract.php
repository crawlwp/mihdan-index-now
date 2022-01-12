<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Logger\Logger;
use Mihdan\IndexNow\Views\WPOSA;
use WP;
use WP_Post;
use WP_Comment;

abstract class IndexNowAbstract implements SearchEngineInterface {

	/**
	 * API key.
	 *
	 * @var string $api_key
	 */
	private $api_key;

	/**
	 * Logger instance.
	 *
	 * @var Logger $logger
	 */
	private $logger;

	/**
	 * WPOSA instance.
	 *
	 * @var WPOSA $wposa
	 */
	private $wposa;

	/**
	 * Site host name.
	 *
	 * @var string $host
	 */
	private $host;

	/**
	 * Post types.
	 *
	 * @var array[]
	 */
	private $post_types;

	/**
	 * IndexNowAbstract constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger, WPOSA $wposa ) {
		$this->logger     = $logger;
		$this->wposa      = $wposa;
		$this->host       = apply_filters( 'mihdan_index_now/host', wp_parse_url( get_home_url(), PHP_URL_HOST ) );
		$this->post_types = apply_filters( 'mihdan_index_now/post_types', (array) $this->wposa->get_option( 'post_types', 'general', [] ) );
		$this->api_key    = $this->wposa->get_option( 'api_key', 'index_now' );
	}

	public function setup_hooks() {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		add_action( 'parse_request', [ $this, 'set_virtual_key_file' ] );
		add_action( 'transition_post_status', [ $this, 'ping_on_post_update' ], 10, 3 );
		add_action( 'wp_insert_comment', [ $this, 'ping_on_insert_comment' ], 10, 2 );
	}

	abstract protected function get_api_url(): string;
	abstract protected function get_bot_useragent(): string;

	public function ping_on_insert_comment( int $id, WP_Comment $comment ) {

		if ( ! $this->is_ping_on_comment() ) {
			return;
		}

		$this->maybe_do_ping( $comment->comment_post_ID );
	}

	public function is_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'index_now', 'on' ) === 'on';
	}

	private function is_ping_on_comment(): bool {
		return $this->wposa->get_option( 'ping_on_comment', 'general', 'on' ) === 'on';
	}

	private function is_key_logging_enabled(): bool {
		return $this->wposa->get_option( 'key_logging', 'logs', 'on' ) === 'on';
	}

	private function get_current_search_engine(): string {
		return $this->wposa->get_option( 'search_engine', 'index_now', 'yandex-index-now' );
	}

	/**
	 * Fires actions related to the transitioning of a post's status.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post    Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function ping_on_post_update( $new_status, $old_status, WP_Post $post ) {

		if ( $new_status !== 'publish' ) {
			return;
		}

		if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
			return;
		}

		$this->maybe_do_ping( $post->ID );
	}

	private function maybe_do_ping( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! in_array( $post->post_type, $this->get_post_types(), true ) ) {
			return;
		}

		if ( $this->get_current_search_engine() === $this->get_slug() ) {
			$this->push( $post );
		}
	}

	public function set_api_url( string $url ): bool {
		$this->api_url = $url;

		return true;
	}

	private function get_post_types(): array {
		return $this->post_types;
	}

	/**
	 * Get host name.
	 *
	 * @return string
	 */
	private function get_host() {
		return $this->host;
	}

	public function push( WP_Post $post ): bool {
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

		$response    = wp_remote_post( $this->get_api_url(), $args );
		$status_code = wp_remote_retrieve_response_code( $response );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$data = [
			'status_code'   => $status_code,
			'search_engine' => $this->get_current_search_engine(),
		];

		if ( Utils::is_response_code_success( $status_code ) ) {
			$message = sprintf( '<a href="%s" target="_blank">%s</a> - OK', get_permalink( $post ), get_the_title( $post ) );
			$this->logger->info( $message, $data );
		} else {
			$this->logger->error( $body['message'] ?? '', $data );
		}

		return true;
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
	 * Set virtual key file.
	 *
	 * @param WP $wp WP instance.
	 */
	public function set_virtual_key_file( WP $wp ) {
		$api_key = $this->get_api_key();

		if ( $wp->request !== $api_key . '.txt' ) {
			return;
		}

		if ( $this->is_key_logging_enabled() ) {
			$data = [
				'search_engine' => $this->get_current_search_engine(),
				'direction'     => 'incoming',
			];

			$this->logger->info( __( 'Bot checked the key file', 'mihdan-index-now' ), $data );
		}

		header( 'Content-Type: text/plain' );
		header( 'X-Robots-Tag: noindex' );
		status_header( 200 );
		echo esc_html( $api_key );
		die;
	}
}
