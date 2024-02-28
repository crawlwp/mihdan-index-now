<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Yandex;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\WebmasterAbstract;

class YandexWebmaster extends WebmasterAbstract {

	private const USER_ENDPOINT    = 'https://api.webmaster.yandex.net/v4/user/';
	private const TOKEN_ENDPOINT   = 'https://oauth.yandex.com/token';
	private const HOSTS_ENDPOINT   = 'https://api.webmaster.yandex.net/v4/user/%d/hosts';
	private const RECRAWL_ENDPOINT = 'https://api.webmaster.yandex.net/v4/user/%s/hosts/%s/recrawl/queue';
	private const QUOTA_ENDPOINT   = 'https://api.webmaster.yandex.net/v4/user/%s/hosts/%s/recrawl/quota';

	public function get_slug(): string {
		return 'yandex-webmaster';
	}

	public function get_name(): string {
		return __( 'Yandex Webmaster', 'mihdan-index-now' );
	}

	public function get_token(): string {
		return $this->wposa->get_option( 'access_token', 'yandex_webmaster' );
	}

	public function get_user_id(): string {
		return $this->wposa->get_option( 'user_id', 'yandex_webmaster' );
	}

	public function get_host_id(): string {
		return $this->wposa->get_option( 'host_id', 'yandex_webmaster' );
	}

	public function get_client_id(): string {
		return $this->wposa->get_option( 'client_id', 'yandex_webmaster' );
	}

	public function get_client_secret(): string {
		return $this->wposa->get_option( 'client_secret', 'yandex_webmaster' );
	}

	public function get_ping_endpoint(): string {
		return self::RECRAWL_ENDPOINT;
	}

	public function get_quota_endpoint(): string {
		return self::QUOTA_ENDPOINT;
	}

	public function is_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'yandex_webmaster', 'off' ) === 'on';
	}

	public function setup_hooks() {

		add_action( 'admin_init', [ $this, 'get_api_token' ] );

		if ( ! $this->is_enabled() ) {
			return;
		}

		//$this->get_quota();
		add_action( 'mihdan_index_now/post_added', [ $this, 'ping' ] );
		add_action( 'mihdan_index_now/post_updated', [ $this, 'ping' ] );
	}

	public function get_api_token() {

		if ( isset( $_GET['code'], $_GET['state'] ) && $_GET['state'] === $this->get_slug() ) {
			$data = [];
			$data['body'] = [
				'grant_type'    => 'authorization_code',
				'code'          => $_GET['code'],
				'client_id'     => $this->get_client_id(),
				'client_secret' => $this->get_client_secret(),
			];

			$response    = wp_remote_post( self::TOKEN_ENDPOINT, $data );
			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $status_code !== 200 ) {
				$this->logger->error( $body['error_description'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
				return;
			}

			$this->wposa->set_option( 'access_token', $body['access_token'], 'yandex_webmaster' );
			$this->wposa->set_option( 'refresh_token', $body['refresh_token'], 'yandex_webmaster' );
			$this->wposa->set_option( 'expires_in', $body['expires_in'] + current_time( 'timestamp' ), 'yandex_webmaster' );

			$user_id = $this->get_api_user_id( $body['access_token'] );

			if ( $user_id ) {
				$this->wposa->set_option( 'user_id', $user_id, 'yandex_webmaster' );

				$host_ids = $this->get_api_host_id( $user_id, $body['access_token'] );

				if ( $host_ids ) {
					$this->wposa->set_option( 'host_ids', serialize( $host_ids ), 'yandex_webmaster' );
				}
			}

			wp_safe_redirect(
				add_query_arg(
					'page',
					Utils::get_plugin_slug(),
					admin_url( 'admin.php' )
				)
			);
		}
	}

	/**
	 * Get user ID.
	 *
	 * @param string $token Access token.
	 *
	 * @return int
	 */
	public function get_api_user_id( string $token ): int {
		$args = [
			'headers' => [
				'Authorization' => 'OAuth ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		$response    = wp_remote_get( self::USER_ENDPOINT, $args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$this->logger->error( $body['error_message'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
			return 0;
		}

		return $body['user_id'] ?? 0;
	}

	/**
	 * Get user ID.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Access token.
	 *
	 * @return int
	 */
	public function get_api_host_id( int $user_id, string $token ): array {
		$args = [
			'headers' => [
				'Authorization' => 'OAuth ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		$response    = wp_remote_get( sprintf( self::HOSTS_ENDPOINT, $user_id ), $args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$this->logger->error( $body['error_message'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
			return 0;
		}

		return isset( $body['hosts'] )
			? wp_list_pluck( $body['hosts'], 'host_id' )
			: [];
	}

	/**
	 * Yandex Webmaster ping.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @link https://yandex.com/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function ping( int $post_id ) {

		$url = sprintf( $this->get_ping_endpoint(), $this->get_user_id(), $this->get_host_id() );

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'OAuth ' . $this->get_token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'url' => get_permalink( $post_id ),
				)
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
			$message = sprintf( '<a href="%s" target="_blank">%s</a> - OK', get_permalink( $post_id ), get_the_title( $post_id ) );
			$this->logger->info( $message, $data );
		} else {
			$this->logger->error( $body['error_message'], $data );
		}
	}

	public function get_quota(): array {
		$url = sprintf( $this->get_quota_endpoint(), $this->get_user_id(), $this->get_host_id() );

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'OAuth ' . $this->get_token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response    = wp_remote_get( $url, $args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		$data = [
			'status_code'   => $status_code,
			'search_engine' => $this->get_slug(),
		];

		if ( Utils::is_response_code_success( $status_code ) ) {
			$message = 'Data on daily limit successfully received';
			$this->logger->info( $message, $data );

			return $body;
		} else {
			$this->logger->error( $body['error_message'], $data );

			return [
				'daily_quota'     => 0,
				'quota_remainder' => 0,
			];
		}
	}
}
