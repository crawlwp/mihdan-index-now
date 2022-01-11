<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Yandex;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\WebmasterAbstract;
use WP_Post;

class YandexWebmaster extends WebmasterAbstract {

	private const CODE_ENDPOINT    = 'https://oauth.yandex.ru/authorize';
	private const USER_ENDPOINT    = 'https://api.webmaster.yandex.net/v4/user/';
	private const TOKEN_ENDPOINT   = 'https://oauth.yandex.ru/token';
	private const HOSTS_ENDPOINT   = 'https://api.webmaster.yandex.net/v4/user/%d/hosts';
	private const RECRAWL_ENDPOINT = 'https://api.webmaster.yandex.net/v4/user/%s/hosts/%s/recrawl/queue';

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

	public function get_ping_endpoint(): string {
		return self::RECRAWL_ENDPOINT;
	}

	public function is_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'yandex_webmaster', 'on' ) === 'on';
	}

	public function setup_hooks() {

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'transition_post_status', [ $this, 'ping_on_post_update' ], 10, 3 );

		//add_action( 'pre_update_option_mihdan_index_now_yandex_webmaster', [ $this, 'get_response_code' ], 10, 2 );
		//add_action( 'update_option_mihdan_index_now_yandex_webmaster', [ $this, 'get_yandex_token' ], 10, 2 );
		//add_action( 'update_option_mihdan_index_now_yandex_webmaster', [ $this, 'maybe_get_user_id' ], 10, 2 );
		//add_action( 'admin_init', [ $this, 'get_api_token' ] );
	}

	public function maybe_get_user_id( $old_value, $value ) {
		if ( $value['enable'] === 'off' ) {
			return;
		}

		if ( ! empty( $value['user_id'] ) ) {
			return;
		}

		if ( empty( $value['token'] ) ) {
			return;
		}

		$this->get_user_id( $value['token'] );
	}

	public function get_response_code( $value, $old_value ) {

		if ( $value['enable'] === 'off' ) {
			return $value;
		}

		if ( empty( $value['client_id'] ) ) {
			return $value;
		}

		if ( empty( $value['client_secret'] ) ) {
			return $value;
		}

		if ( ! empty( $value['access_token'] ) ) {
			return $value;
		}

		$url = sprintf(
			'%s?response_type=code&client_id=%s&redirect_uri=%s&force_confirm=yes&display=popup&state=%s',
			self::CODE_ENDPOINT,
			$value['client_id'],
			rawurldecode( admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ) ),
			$this->get_slug()
		);
		wp_redirect( $url );
		die;
	}

	public function get_api_token() {

		if ( isset( $_GET['code'], $_GET['state'] ) && $_GET['state'] === $this->get_slug() ) {
			$data = [];
			$data['body'] = [
				'grant_type'    => 'authorization_code',
				'code'          => $_GET['code'],
				'client_id'     => '08c41fd597854d47b2911716d7f71e2f',
				'client_secret' => '2a4c5831b44e469f8a86c36fd88101f6',
			];

			$response    = wp_remote_post( self::TOKEN_ENDPOINT, $data );
			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $status_code !== 200 ) {
				$this->error( $body['error_description'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
				return;
			}

			$user_id = $this->get_user_id( $body['access_token'] );
			$host_id = $this->get_host_id( $user_id, $body['access_token'] );

			print_r($body['access_token']);
			print_r($user_id);
			print_r($host_id);

			//$body['access_token']
			//$body['expires_in']
			//$body['refresh_token']
			//$body['token_type']
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
			$this->error( $body['error_message'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
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
			$this->error( $body['error_message'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
			return 0;
		}

		return isset( $body['hosts'] )
			? wp_list_pluck( $body['hosts'], 'unicode_host_url', 'host_id' )
			: [];
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

		$this->ping( $post );
	}

	/**
	 * Yandex Webmaster ping.
	 *
	 * @param WP_Post $post WP_Post unstance.
	 *
	 * @link https://yandex.com/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function ping( WP_Post $post ) {

		$url = sprintf( $this->get_ping_endpoint(), $this->get_user_id(), $this->get_host_id() );

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'OAuth ' . $this->get_token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'url' => get_permalink( $post->ID ),
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
			$message = sprintf( '<a href="%s" target="_blank">%s</a> - OK', get_permalink( $post ), get_the_title( $post ) );
			$this->logger->info( $message, $data );
		} else {
			$this->logger->error( $body['error_message'], $data );
		}
	}
}
