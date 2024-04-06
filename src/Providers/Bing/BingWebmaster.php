<?php
/**
 * IndexNow via Bing.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Bing;

use Mihdan\IndexNow\WebmasterAbstract;
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

		add_action( 'mihdan_index_now/post_added', [ $this, 'ping' ] );
		add_action( 'mihdan_index_now/post_updated', [ $this, 'ping' ] );

		/** @todo similar to IndexNowAbstract, add support for the below trigger */
//		if ( $this->is_ping_on_comment() ) {
//			add_action( 'mihdan_index_now/comment_updated', [ $this, 'ping_on_insert_comment' ], 10, 2 );
//		}
//
//		if ( $this->is_ping_on_term() ) {
//			add_action( 'mihdan_index_now/term_updated', [ $this, 'ping_on_insert_term' ], 10, 2 );
//		}
	}

	/**
	 * Bing Webmaster ping.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @link https://www.bing.com/webmasters/url-submission-api#APIs
	 */
	public function ping( int $post_id ) {
		$url  = sprintf( $this->get_ping_endpoint(), $this->get_token() );

		$post_url = $this->normalize_url(get_permalink( $post_id ));

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode(
				[
					'siteUrl' => get_home_url(),
					'urlList' => [
						$post_url,
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
			$message = sprintf( '<a href="%s" target="_blank">%s</a> - OK', $post_url, get_the_title( $post_id ) );
			$this->logger->info( $message, $data );
		} else {
			$this->logger->error( $body['Message'], $data );
		}

		do_action('mihdan_index_now/index_pinged', 'post', $post_id);
	}

	public function get_quota(): array {
		// TODO: Implement get_quota() method.
		return [];
	}
}
