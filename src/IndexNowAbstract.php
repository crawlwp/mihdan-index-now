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
	 * Taxonomies.
	 *
	 * @var array[]
	 */
	private $taxonomies;

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
		$this->taxonomies = apply_filters( 'mihdan_index_now/taxonomies', (array) $this->wposa->get_option( 'taxonomies', 'general', [] ) );
		$this->api_key    = $this->wposa->get_option( 'api_key', 'index_now', Utils::generate_key() );
	}

	public function setup_hooks() {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		add_action( 'parse_request', [ $this, 'set_virtual_key_file' ] );

		if ( $this->is_ping_on_post() ) {
			add_action( 'mihdan_index_now/post_updated', [ $this, 'ping_on_post_update' ], 10, 2 );
		}

		if ( $this->is_ping_on_comment() ) {
			add_action( 'mihdan_index_now/comment_updated', [ $this, 'ping_on_insert_comment' ], 10, 2 );
		}

		if ( $this->is_ping_on_term() ) {
			add_action( 'mihdan_index_now/term_updated', [ $this, 'ping_on_insert_term' ], 10, 2 );
		}
	}

	abstract protected function get_api_url(): string;
	abstract protected function get_bot_useragent(): string;

	public function is_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'index_now', 'on' ) === 'on';
	}

	private function is_ping_on_comment(): bool {
		return $this->wposa->get_option( 'ping_on_comment', 'general', 'off' ) === 'on';
	}

	private function is_ping_on_post(): bool {
		return $this->wposa->get_option( 'ping_on_post', 'general', 'on' ) === 'on';
	}

	private function is_ping_on_term(): bool {
		return $this->wposa->get_option( 'ping_on_term', 'general', 'off' ) === 'on';
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
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function ping_on_post_update( int $post_id, WP_Post $post ) {
		$this->maybe_do_ping_post( $post_id );
	}

	public function ping_on_insert_comment( int $post_id, WP_Comment $comment ) {
		$this->maybe_do_ping_post( $post_id );
	}

	public function ping_on_insert_term( int $term_id, string $taxonomy ) {
		$this->maybe_do_ping_term( $term_id, $taxonomy );
	}

	private function maybe_do_ping_post( int $post_id ) {

		if ( $this->get_current_search_engine() === $this->get_slug() ) {
			$this->push( [ get_permalink( $post_id ) ] );
		}
	}

	private function maybe_do_ping_term( int $term_id, string $taxonomy ) {

		if ( ! in_array( $taxonomy, $this->get_taxonomies(), true ) ) {
			return;
		}

		if ( $this->get_current_search_engine() === $this->get_slug() ) {
			$this->push( [ get_term_link( $term_id, $taxonomy ) ] );
		}
	}

	public function set_api_url( string $url ): bool {
		$this->api_url = $url;

		return true;
	}

	private function get_post_types(): array {
		return $this->post_types;
	}

	private function get_taxonomies(): array {
		return $this->taxonomies;
	}

	/**
	 * Get host name.
	 *
	 * @return string
	 */
	private function get_host() {
		return $this->host;
	}

	public function push( array $url_list ): bool {
		$args = array(
			'timeout' => 30,
			'body'    => wp_json_encode(
				array(
					'host'    => $this->get_host(),
					'key'     => $this->get_api_key(),
					'urlList' => $url_list,
				)
			),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		);

		$response    = wp_remote_post( $this->get_api_url(), $args );
		$status_code = wp_remote_retrieve_response_code( $response );

		$body = wp_remote_retrieve_body( $response );

		if ( Utils::is_json( $body ) ) {
			$body = json_decode( $body, true );
		}

		$data = [
			'status_code'   => $status_code,
			'search_engine' => $this->get_current_search_engine(),
		];

		if ( Utils::is_response_code_success( $status_code ) ) {
			foreach ( $url_list as $url ) {
				$message = sprintf( '<a href="%s" target="_blank">%s</a> - OK', $url, $url );
				$this->logger->info( $message, $data );
			}
		} else {
			$this->logger->error( $body['message'] ?? print_r( $body, 1 ), $data );
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
