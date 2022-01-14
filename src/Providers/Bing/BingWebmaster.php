<?php
/**
 * IndexNow via Bing.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Bing;

use Mihdan\IndexNow\WebmasterAbstract;
use WP_Post;
use Mihdan\IndexNow\Utils;

class BingWebmaster extends WebmasterAbstract {
	private const RECRAWL_ENDPOINT = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=%s';

	public function get_ping_endpoint(): string {
		return self::RECRAWL_ENDPOINT;
	}

	public function get_slug(): string {
		return 'bing-webmaster';
	}

	public function get_name(): string {
		return __( 'Bing Webmaster', 'mihdan-index-now' );
	}

	public function get_token(): string {
		return $this->wposa->get_option( 'api_key', 'bing_webmaster' );
	}

	public function is_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'bing_webmaster', 'off' ) === 'on';
	}

	public function setup_hooks() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'transition_post_status', [ $this, 'ping_on_post_update' ], 10, 3 );
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

		$this->ping( $post );
	}

	/**
	 * Bing Webmaster ping.
	 *
	 * @param WP_Post $post WP_Post instance.
	 *
	 * @link https://www.bing.com/webmasters/url-submission-api#APIs
	 */
	public function ping( WP_Post $post ) {
		$url  = sprintf( $this->get_ping_endpoint(), $this->get_token() );
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode(
				[
					'siteUrl' => get_home_url(),
					'urlList' => [
						get_permalink( $post->ID ),
					],
				]
			),
		);

		$response    = wp_remote_post( $url, $args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		$data = [
			'status_code'   => $status_code,
			'search_engine' => $this->get_slug(),
		];

		if ( Utils::is_response_code_success( $status_code ) ) {
			$message = sprintf( '<a href="%s" target="_blank">%s</a> - OK', get_permalink( $post ), get_the_title( $post ) );
			$this->logger->info( $message, $data );
		} else {
			$this->logger->error( $body['Message'], $data );
		}
	}
}
